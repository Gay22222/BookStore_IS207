<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Address;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Book;
use Exception;

class OrderController extends Controller
{
    public function placeOrder(Request $request, string $paymentMethod)
    {
        try {
            $data = $request->validate([
                'addressId'          => ['required','integer','min:1'],
                'pgName'             => ['sometimes','nullable','string'],
                'pgPaymentId'        => ['sometimes','nullable','string'],
                'pgStatus'           => ['sometimes','nullable','string'],
                'pgResponseMessage'  => ['sometimes','nullable','string'],
            ]);

            $user = auth('api')->user();

            $result = DB::transaction(function () use ($user, $paymentMethod, $data) {
                $cart = Cart::with('cartItems.book')->where('user_id', $user->id)->first();
                if (!$cart) {
                    return $this->errorResponse(404, 'Cart not found', ['cart' => ['Cart not found.']]);
                }
                if ($cart->cartItems->isEmpty()) {
                    return $this->errorResponse(400, 'Cart is empty', ['cart' => ['No items in cart.']]);
                }

                $address = Address::where('id', $data['addressId'])->where('user_id', $user->id)->first();
                if (!$address) {
                    return $this->errorResponse(404, 'Address not found', ['address' => ['Address not found for user.']]);
                }

                $payment = new Payment([
                    'payment_method'      => $paymentMethod,
                    'pg_payment_id'       => $data['pgPaymentId'] ?? null,
                    'pg_status'           => $data['pgStatus'] ?? null,
                    'pg_response_message' => $data['pgResponseMessage'] ?? null,
                    'pg_name'             => $data['pgName'] ?? null,
                ]);
                $payment->save();

                $order = new Order();
                $order->email = $user->email;
                $order->order_date = now()->toDateString();
                $order->payment_id = $payment->id;
                $order->address_id = $address->id;
                $order->total_amount = $cart->total_price ?? 0;
                $order->order_status = 'ACCEPTED';
                $order->save();

                if (empty($order->order_code)) {
    $order->order_code = $this->generateOrderCode($order->id);
    $order->save();
}

                $itemsPayload = [];
                foreach ($cart->cartItems as $ci) {
                    $book = $ci->book;
                    if (!$book) {
                        return $this->errorResponse(400, 'Invalid cart item', ['cart' => ['Book missing for cart item.']]);
                    }
                    if ($book->quantity < $ci->quantity) {
                        return $this->errorResponse(400, 'Quantity exceeds stock', ['stock' => ["{$book->title} exceeds stock."]]);
                    }
                    $itemsPayload[] = [
                        'order_id'           => $order->id,
                        'book_id'            => $book->id,
                        'quantity'           => $ci->quantity,
                        'discount'           => $ci->discount,
                        'ordered_book_price' => $ci->book_price,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                }

                if (!empty($itemsPayload)) {
                    OrderItem::insert($itemsPayload);
                }

                foreach ($cart->cartItems as $ci) {
                    $book = $ci->book;
                    $book->quantity = max(0, $book->quantity - $ci->quantity);
                    $book->save();
                }

                CartItem::where('cart_id', $cart->id)->delete();
                $cart->total_price = 0;
                $cart->save();

                $order = Order::with(['orderItems.book', 'payment', 'address'])->find($order->id);
                return response()->json($this->orderResponse($order), 201);
            });

            return $result;
        } catch (ValidationException $e) {
            return $this->errorResponse(422, 'Validation failed', $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(404, 'Not found', ['resource' => ['Resource not found.']]);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllOrders()
    {
        try {
            $user = auth('api')->user();
            if (!$this->isAdminOrEmployee($user->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Insufficient permissions.']]);
            }

            $orders = Order::with(['orderItems.book', 'address', 'payment'])->orderByDesc('order_date')->get();
            $list = [];
            foreach ($orders as $o) {
                $list[] = $this->orderResponse($o);
            }
            return response()->json($list);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getOrderById(int $id)
    {
        try {
            $user = auth('api')->user();
            if (!$this->isAdminOrEmployee($user->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Insufficient permissions.']]);
            }

            $order = Order::with(['orderItems.book', 'address', 'payment'])->findOrFail($id);
            return response()->json($this->orderResponse($order));
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(404, 'Order not found', ['order' => ['Order not found.']]);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function updateOrderStatus(Request $request, int $orderId)
    {
        try {
            $data = $request->validate([
                'orderStatus' => ['required','string'],
            ]);

            $user = auth('api')->user();
            if (!$this->isAdminOrEmployee($user->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Insufficient permissions.']]);
            }

            $status = strtoupper(trim($data['orderStatus']));
            if (!in_array($status, ['SHIPPING','COMPLETED'])) {
                return $this->errorResponse(400, 'Invalid order status', ['orderStatus' => ['Allowed: SHIPPING, COMPLETED']]);
            }

            $order = Order::find($orderId);
            if (!$order) {
                return $this->errorResponse(404, 'Order not found', ['order' => ['Order not found.']]);
            }

            $order->order_status = $status;
            $order->save();

            $order = Order::with(['orderItems.book', 'address', 'payment'])->find($order->id);
            return response()->json($this->orderResponse($order));
        } catch (ValidationException $e) {
            return $this->errorResponse(422, 'Validation failed', $e->errors());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getOrderByCode(string $orderCode)
{
    try {
        $user = auth('api')->user();
        if (!$user) {
            return $this->errorResponse(401, 'Unauthenticated', [
                'auth' => ['Unauthenticated.'],
            ]);
        }

        // Tìm order theo order_code
        $order = Order::with(['orderItems.book', 'address', 'payment'])
            ->where('order_code', $orderCode)
            ->firstOrFail();

        if (
            !$this->isAdminOrEmployee($user->id) &&
            $order->email !== $user->email
        ) {
            return $this->errorResponse(403, 'Access denied', [
                'auth' => ['Insufficient permissions.'],
            ]);
        }

        return response()->json($this->orderResponse($order));
    } catch (ModelNotFoundException $e) {
        return $this->errorResponse(404, 'Order not found', [
            'order' => ['Order not found.'],
        ]);
    } catch (QueryException $e) {
        return $this->errorResponse(500, 'Database error', [
            'database' => [$e->getMessage()],
        ]);
    } catch (Exception $e) {
        return $this->errorResponse(500, 'Server error', [
            'server' => [$e->getMessage()],
        ]);
    }
}


    private function isAdminOrEmployee(int $userId): bool
    {
        $roles = DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->pluck('roles.name')
            ->map(fn ($r) => strtoupper($r))
            ->toArray();
        return in_array('ADMIN', $roles, true) || in_array('EMPLOYEE', $roles, true);
    }

    private function orderResponse(Order $order): array
{
    $items = [];
    foreach ($order->orderItems as $oi) {
        $items[] = [
            'orderItemId'       => $oi->id,
            'bookId'            => $oi->book_id,
            'title'             => optional($oi->book)->title,
            'quantity'          => $oi->quantity,
            'discount'          => $oi->discount,
            'orderedBookPrice'  => $oi->ordered_book_price,
            'imageUrl'          => optional($oi->book)->image_url,
        ];
    }

    return [
        'orderId'        => $order->id,
        'orderCode'      => $order->order_code,              // <- thêm
        'email'          => $order->email,
        'orderDate'      => $order->order_date,
        'createdAt'      => optional($order->created_at)?->toIso8601String(),
        'paidAt'         => optional($order->paid_at)?->toIso8601String(),
        'totalAmount'    => $order->total_amount,
        'orderStatus'    => $order->order_status,
        'paymentStatus'  => $order->payment_status,          // <- thêm
        'addressId'      => $order->address_id,
        'payment'        => $order->payment ? [
            'paymentId'         => $order->payment->id,
            'paymentMethod'     => $order->payment->payment_method,
            'pgPaymentId'       => $order->payment->pg_payment_id,
            'pgStatus'          => $order->payment->pg_status,
            'pgResponseMessage' => $order->payment->pg_response_message,
            'pgName'            => $order->payment->pg_name,
        ] : null,
        'orderItems'     => $items,
    ];
}


    private function errorResponse(int $status, string $message, array $errors = [])
    {
        return response()->json([
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }

    private function generateOrderCode(int $orderId): string
{
    do {
        $code = 'ORD' . now()->format('Ymd')
            . '-' . str_pad((string)$orderId, 8, '0', STR_PAD_LEFT)
            . '-' . strtoupper(Str::random(4));
    } while (Order::where('order_code', $code)->exists());

    return $code;
}
}

    
