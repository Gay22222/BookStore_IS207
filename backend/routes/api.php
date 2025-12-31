<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Payment\VnpayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('books')->group(function () {
    Route::get('/', [BookController::class, 'index']);
    Route::get('/book', [BookController::class, 'getBookById']); // ?Id=...
    Route::get('/search', [BookController::class, 'search']);
    Route::get('/searchTitle', [BookController::class, 'searchTitle']);
});

Route::post('/auth/signup', [AuthController::class, 'signup']);
Route::post('/auth/signin', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/change-password', [AuthController::class, 'changePassword'])
    ->middleware('auth:api');


Route::middleware('auth:api')->group(function () {
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::patch('/addresses/{addressId}', [AddressController::class, 'update']);
    Route::delete('/addresses/{addressId}', [AddressController::class, 'destroy']);

    Route::post('/carts/books/{bookId}/quantity/{quantity}', [CartController::class, 'addProduct'])
        ->name('carts.add-product');

    Route::get('/carts/users/cart', [CartController::class, 'getUsersCart'])
        ->name('carts.me');

    Route::patch('/carts/book/{bookId}/quantity/{operation}', [CartController::class, 'updateBookQuantity'])
        ->whereIn('operation', ['add', 'delete'])
        ->name('carts.update-qty');

    Route::delete('/carts/book/{bookId}', [CartController::class, 'deleteBook'])
        ->name('carts.delete-book');

    Route::get('/carts/total-items', [CartController::class, 'totalItems'])
        ->name('carts.total-items');

    Route::post('/order/users/payments/{paymentMethod}', [OrderController::class, 'placeOrder'])
        ->name('order.place');

    Route::get('/order', [OrderController::class, 'getAllOrders'])
        ->name('order.index');

    Route::get('/order/{id}', [OrderController::class, 'getOrderById'])
        ->whereNumber('id')
        ->name('order.show');

    Route::patch('/order/{orderId}/status', [OrderController::class, 'updateOrderStatus'])
        ->whereNumber('orderId')
        ->name('order.update-status');

    Route::get('/orders/by-code/{orderCode}', [OrderController::class, 'getOrderByCode']);

    Route::prefix('manage')->group(function () {
        Route::get('/dashboard-stats', [AdminController::class, 'dashboardStats'])
            ->name('manage.dashboard.stats');

        Route::post('/user', [AdminController::class, 'createUser'])
            ->name('manage.user.create');

        Route::get('/get-all-users', [AdminController::class, 'getAllUsers'])
            ->name('manage.users.all');

        Route::get('/get-all-customers', [AdminController::class, 'getAllCustomers'])
            ->name('manage.users.customers');

        Route::get('/get-all-employees', [AdminController::class, 'getAllEmployees'])
            ->name('manage.users.employees');

        Route::get('/search/users', [AdminController::class, 'searchUsers'])
            ->name('manage.search.users');

        Route::get('/search/customers', [AdminController::class, 'searchCustomers'])
            ->name('manage.search.customers');

        Route::get('/search/employees', [AdminController::class, 'searchEmployees'])
            ->name('manage.search.employees');

        Route::patch('/user', [AdminController::class, 'updateUser'])
            ->name('manage.user.update');

        Route::delete('/user/{id}', [AdminController::class, 'deleteUser'])
            ->whereNumber('id')
            ->name('manage.user.delete');

        Route::prefix('books')->group(function () {
            Route::get('/', [AdminController::class, 'getAllBooks'])
                ->name('manage.books.all');

            Route::get('/book', [AdminController::class, 'getBookById'])
                ->name('manage.books.by-id'); // ?Id=...

            Route::post('/', [AdminController::class, 'createBook'])
                ->name('manage.books.create');

            Route::patch('/', [AdminController::class, 'updateBook'])
                ->name('manage.books.update'); // ?id=...

            Route::delete('/', [AdminController::class, 'deleteBook'])
                ->name('manage.books.delete'); // ?id=...

            Route::get('/search', [AdminController::class, 'searchBooks'])
                ->name('manage.books.search');

            Route::get('/searchTitle', [AdminController::class, 'searchBookTitle'])
                ->name('manage.books.searchTitle');
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminController::class, 'getAllOrders'])
                ->name('manage.orders.all');

            Route::get('/{id}', [AdminController::class, 'getOrderById'])
                ->whereNumber('id')
                ->name('manage.orders.by-id');

            Route::get('/by-code/{orderCode}', [AdminController::class, 'getOrderByCode'])
                ->name('manage.orders.by-code');

            Route::patch('/{orderId}/status', [AdminController::class, 'updateOrderStatus'])
                ->whereNumber('orderId')
                ->name('manage.orders.update-status');

            Route::patch('/{orderId}/payment-status', [AdminController::class, 'updatePaymentStatus'])
                ->whereNumber('orderId')
                ->name('manage.orders.update-payment-status');
            Route::get('/search', [AdminController::class, 'searchOrdersByCode'])
    ->name('manage.orders.search');
        });
    });

    Route::get('/user/me', [UserController::class, 'me'])
        ->name('user.me');

    Route::get('/user/my-orders', [UserController::class, 'myOrders'])
        ->name('user.my-orders');

    Route::patch('/user/me', [UserController::class, 'updateMe'])
        ->name('user.update-me');

    Route::post('/payments/vnpay/create', [VnpayController::class, 'create']);
});

Route::get('/payments/vnpay/return', [VnpayController::class, 'return']);
Route::get('/payments/vnpay/ipn', [VnpayController::class, 'ipn']);
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])
            ->middleware('throttle:10,1');