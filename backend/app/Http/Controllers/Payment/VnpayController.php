<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\{
    Order,
    OrderItem,
    Payment,
    Cart,
    CartItem,
    Address,
    Book,
    User
};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class VnpayController extends Controller
{
    public function create(Request $request)
{
    Log::info('VNPAY_CREATE_REQUEST_IN', [
        'ip'        => $request->ip(),
        'payload'   => $request->all(),
        'user_id'   => optional(auth('api')->user())->id,
    ]);

    $data = $request->validate([
        'addressId' => ['required', 'integer', 'min:1'],
    ]);

    $user = auth('api')->user();
    if (!$user) {
        Log::warning('VNPAY_CREATE_NO_AUTH_USER');
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $ipAddr = $request->ip();

    try {
        return DB::transaction(function () use ($user, $data, $ipAddr) {

            $cart = Cart::with('cartItems.book')
                ->where('user_id', $user->id)
                ->first();

            if (!$cart || $cart->cartItems->isEmpty()) {
                Log::warning('VNPAY_CREATE_CART_EMPTY', [
                    'user_id' => $user->id,
                ]);
                return response()->json(['message' => 'Cart empty'], 400);
            }

            $address = Address::where('id', $data['addressId'])
                ->where('user_id', $user->id)
                ->firstOrFail();

            Log::info('VNPAY_CREATE_CART_ADDRESS_OK', [
                'user_id'    => $user->id,
                'cart_id'    => $cart->id,
                'address_id' => $address->id,
            ]);

            // 1. Payment PENDING
            $payment = Payment::create([
                'payment_method' => 'VNPAY',
                'pg_status'      => 'PENDING',
                'pg_name'        => 'VNPAY',
            ]);

            // 2. Order PENDING
            $order = Order::create([
                'email'          => $user->email,
                'order_date'     => now()->toDateString(),
                'payment_id'     => $payment->id,
                'address_id'     => $address->id,
                'total_amount'   => $cart->total_price,
                'order_status'   => 'ACCEPTED',
                'payment_status' => 'PENDING',
                'order_code'     => strtoupper(Str::ulid()),
            ]);

            Log::info('VNPAY_CREATE_ORDER_PAYMENT_CREATED', [
                'user_id'     => $user->id,
                'order_id'    => $order->id,
                'order_code'  => $order->order_code,
                'payment_id'  => $payment->id,
                'total_usd'   => $order->total_amount,
            ]);

            // 3. Copy cart items sang order_items
            $itemsPayload = [];
            foreach ($cart->cartItems as $ci) {
                if (!$ci->book) {
                    Log::error('VNPAY_CREATE_INVALID_CART_ITEM', [
                        'cart_id'   => $cart->id,
                        'cart_item' => $ci->id ?? null,
                    ]);
                    return response()->json(['message' => 'Invalid cart item'], 400);
                }

                $itemsPayload[] = [
                    'order_id'           => $order->id,
                    'book_id'            => $ci->book->id,
                    'quantity'           => $ci->quantity,
                    'discount'           => $ci->discount,
                    'ordered_book_price' => $ci->book_price,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
            OrderItem::insert($itemsPayload);

            Log::info('VNPAY_CREATE_ORDER_ITEMS_INSERTED', [
                'order_id' => $order->id,
                'items'    => count($itemsPayload),
            ]);

            // 4. Build URL thanh toán VNPAY 
            $vnp_TmnCode    = env('VNP_TMN_CODE');
            $vnp_HashSecret = trim(env('VNP_HASH_SECRET')); // trim luôn cho chắc
            $vnp_Url        = env('VNP_URL');          
            $vnp_ReturnUrl  = env('VNP_RETURN_URL');  

            Log::info('VNPAY_CREATE_ENV_VALUES', [
                'vnp_TmnCode'   => $vnp_TmnCode,
                'vnp_Url'       => $vnp_Url,
                'vnp_ReturnUrl' => $vnp_ReturnUrl,
                'hash_len'      => $vnp_HashSecret ? strlen($vnp_HashSecret) : 0,
            ]);

            $usdAmount = (float) $order->total_amount;

            // Convert sang VND bằng API
            Log::info('VNPAY_CREATE_FX_CALL', [
                'amount_usd' => $usdAmount,
            ]);

            $vndAmount = $this->usdToVnd($usdAmount);

            Log::info('VNPAY_CREATE_FX_RESULT', [
                'amount_usd' => $usdAmount,
                'amount_vnd' => $vndAmount,
            ]);

            $order->total_amount_vnd = $vndAmount;
            $order->save();

            // sửa luôn: check object thay vì property_exists để tránh lỗi null
            if ($order->payment) {
                $order->payment->pg_amount = $vndAmount;
                $order->payment->save();
            }

            $vnp_TxnRef = $order->order_code;
            $vnp_Amount = $vndAmount * 100; 

            $vnp_OrderInfo = 'Thanh toan don hang #' . $order->order_code;
            $vnp_OrderType = 'billpayment';
            $vnp_Locale    = 'vn';
            $vnp_IpAddr    = $ipAddr;

            $inputData = [
                "vnp_Version"    => "2.1.0",
                "vnp_TmnCode"    => $vnp_TmnCode,
                "vnp_Amount"     => $vnp_Amount,
                "vnp_Command"    => "pay",
                "vnp_CreateDate" => now()->format('YmdHis'),
                "vnp_CurrCode"   => "VND",
                "vnp_IpAddr"     => $vnp_IpAddr,
                "vnp_Locale"     => $vnp_Locale,
                "vnp_OrderInfo"  => $vnp_OrderInfo,
                "vnp_OrderType"  => $vnp_OrderType,
                "vnp_ReturnUrl"  => $vnp_ReturnUrl,
                "vnp_TxnRef"     => $vnp_TxnRef,
                // "vnp_BankCode"   => "VNPAYQR",
            ];

            Log::info('VNPAY_CREATE_INPUT_DATA_BEFORE_SORT', [
                'inputData' => $inputData,
            ]);

            ksort($inputData);
            $query    = "";
            $hashdata = "";
            $i        = 0;
            foreach ($inputData as $key => $value) {
                if ($i == 1) {
                    $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
                } else {
                    $hashdata .= urlencode($key) . "=" . urlencode($value);
                    $i = 1;
                }
                $query .= urlencode($key) . "=" . urlencode($value) . '&';
            }

            $vnpUrl = $vnp_Url . "?" . $query;
            if ($vnp_HashSecret) {
                $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
                $vnpUrl       .= 'vnp_SecureHash=' . $vnpSecureHash;
            }

            Log::info('VNPAY_CREATE_HASH', [
                'hashData'    => $hashdata,
                'vnp_Url'     => $vnp_Url,
                'final_vnpUrl'=> $vnpUrl,
            ]);

            return response()->json([
                'code'      => '00',
                'message'   => 'success',
                'data'      => $vnpUrl,
                'orderCode' => $order->order_code,
            ]);
        });
    } catch (\Throwable $e) {
        Log::error('VNPAY_CREATE_EXCEPTION', [
            'message' => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        return response()->json([
            'message' => 'Có lỗi xảy ra khi tạo giao dịch VNPAY',
        ], 500);
    }
}



    /**
     * Return URL: chỉ để điều hướng FE, không dùng để xác nhận thanh toán.
     */
    public function return(Request $request)
    {
        $orderCode = $request->input('vnp_TxnRef');
        $frontend  = rtrim(env('APP_FRONTEND', ''), '/');

        // FE sẽ dùng orderCode này để gọi API xem trạng thái thực (PAID/PENDING)
        return redirect($frontend . '/checkout/vnpay/result?orderCode=' . urlencode($orderCode));
    }

    /*
     *  1. Verify chữ ký
     *  2. Xác nhận trạng thái (vnp_ResponseCode, vnp_TransactionStatus)
     *  3. Đối soát số tiền
     *  4. Nếu ok -> trừ kho, dọn giỏ, set PAID
     */
    public function ipn(Request $request)
{
    $vnp_HashSecret = env('VNP_HASH_SECRET');

    // Lấy toàn bộ tham số vnp_*
    $inputData = [];
    foreach ($request->all() as $key => $value) {
        if (str_starts_with($key, 'vnp_')) {
            $inputData[$key] = $value;
        }
    }

    $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? null;
    unset($inputData['vnp_SecureHash'], $inputData['vnp_SecureHashType']);

    ksort($inputData);
    $hashdata = "";
    $i        = 0;
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashdata .= urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }

    $calcHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

    Log::info('VNPAY_IPN_VERIFY', [
        'hashData' => $hashdata,
        'recvHash' => $vnp_SecureHash,
        'calcHash' => $calcHash,
    ]);

    if (!$vnp_SecureHash || $calcHash !== $vnp_SecureHash) {
        return response()->json(['RspCode' => '97', 'Message' => 'Invalid signature']);
    }

    $orderCode       = $inputData['vnp_TxnRef']             ?? null;
    $responseCode    = $inputData['vnp_ResponseCode']       ?? null;
    $transactionStat = $inputData['vnp_TransactionStatus']  ?? null;
    $amount          = isset($inputData['vnp_Amount']) ? (int)$inputData['vnp_Amount'] : 0;

    if (!$orderCode) {
        return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
    }

    $order = Order::with(['payment', 'orderItems.book'])
        ->where('order_code', $orderCode)
        ->lockForUpdate()
        ->first();

    if (!$order || !$order->payment) {
        return response()->json(['RspCode' => '01', 'Message' => 'Order not found']);
    }

    if ($order->payment_status === 'PAID') {
        return response()->json(['RspCode' => '00', 'Message' => 'Order already confirmed']);
    }

    if ($responseCode !== '00' || $transactionStat !== '00') {
        $order->payment_status = 'FAILED';
        $order->payment->pg_status = 'FAILED';
        $order->payment->pg_response_message = 'Payment failed: ' . $responseCode . '/' . $transactionStat;
        $order->payment->save();
        $order->save();

        return response()->json(['RspCode' => '00', 'Message' => 'Payment failed']);
    }

    $expectedAmount = (int) ($order->total_amount_vnd * 100);
    if ($amount !== $expectedAmount) {
    return response()->json(['RspCode' => '04', 'Message' => 'Invalid amount']);
    }


    DB::transaction(function () use ($order) {
        // 1. Trừ kho
        foreach ($order->orderItems as $item) {
            $book = $item->book;
            if (!$book) {
                continue;
            }
            if ($book->quantity < $item->quantity) {
                continue;
            }
            $book->quantity = max(0, $book->quantity - $item->quantity);
            $book->save();
        }

        // 2. Dọn giỏ
        $userId = User::where('email', $order->email)->value('id');
        if ($userId) {
            $cart = Cart::with('cartItems')->where('user_id', $userId)->first();
            if ($cart) {
                CartItem::where('cart_id', $cart->id)->delete();
                $cart->total_price = 0;
                $cart->save();
            }
        }

        // 3. Cập nhật trạng thái thanh toán
        $order->payment_status = 'PAID';
        $order->paid_at        = now();
        $order->payment->pg_status = 'SUCCESS';
        $order->payment->pg_response_message = 'Payment success';
        $order->payment->save();
        $order->save();
    });

    return response()->json(['RspCode' => '00', 'Message' => 'Confirm success']);
}

//HELPER
    protected function usdToVnd(float $amountUsd): int
{
    $url  = config('services.fx.url');
    $from = config('services.fx.from', 'USD');
    $to   = config('services.fx.to', 'VND');
    $key  = config('services.fx.key');

    $params = [
        'from'   => $from,
        'to'     => $to,
        'amount' => $amountUsd,
    ];

    if ($key) {
        $params['access_key'] = $key;
    }

    try {
        $client = Http::timeout(5);

        if (app()->environment('local')) {
            $client = $client->withoutVerifying();
        }

        $res = $client->get($url, $params);
    } catch (\Throwable $e) {
        Log::error('FX_API_ERROR', [
            'message' => $e->getMessage(),
        ]);
        throw new \RuntimeException('Không lấy được tỉ giá, vui lòng thử lại');
    }

    if (!$res->ok()) {
        Log::error('FX_API_ERROR_STATUS', [
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);
        throw new \RuntimeException('Không lấy được tỉ giá, vui lòng thử lại');
    }

    $data = $res->json();

    // exchangerate.host/convert: cần success = true và có result
    if (
        empty($data['success']) ||
        $data['success'] !== true ||
        !isset($data['result'])
    ) {
        Log::error('FX_API_INVALID_RESPONSE', ['data' => $data]);
        throw new \RuntimeException('FX API trả về sai định dạng');
    }

    // result = số VND tương ứng với $amountUsd
    return (int) round($data['result']);
}


}
