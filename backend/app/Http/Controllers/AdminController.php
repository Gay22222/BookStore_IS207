<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use Exception;

class AdminController extends Controller
{
    public function dashboardStats(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $usersCount = User::count();
            $customersCount = User::whereHas('roles', function ($q) {
                $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_USER', 'USER']);
            })->count();
            $employeesCount = User::whereHas('roles', function ($q) {
                $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_EMPLOYEE', 'EMPLOYEE', 'ROLE_EMPLOYEES', 'EMPLOYEES']);
            })->count();
            $booksCount = Book::count();
            $ordersCount = Order::count();

            $revenueAll = (float) (Order::sum('total_amount') ?? 0);
            $revenuePaid = (float) (Order::whereIn(DB::raw('UPPER(payment_status)'), ['PAID', 'SUCCESS'])->sum('total_amount') ?? 0);

            return response()->json([
                'usersCount' => $usersCount,
                'customersCount' => $customersCount,
                'employeesCount' => $employeesCount,
                'booksCount' => $booksCount,
                'ordersCount' => $ordersCount,
                'revenueAll' => $revenueAll,
                'revenuePaid' => $revenuePaid,
            ]);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function createUser(Request $request)
    {
        try {
            $data = $request->validate([
                'userName' => ['required','string','min:2','max:20', Rule::unique('users','user_name')],
                'email' => ['required','email','max:50', Rule::unique('users','email')],
                'password' => ['required','string','min:6','max:100'],
                'roles' => ['sometimes','array'],
                'roles.*' => ['string','min:2','max:50'],
            ]);

            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $username = mb_strtolower(trim($data['userName']), 'UTF-8');
            $email = mb_strtolower(trim($data['email']), 'UTF-8');

            [$user] = DB::transaction(function () use ($username, $email, $data) {
                $user = User::create([
                    'user_name' => $username,
                    'email' => $email,
                    'password' => $data['password'],
                ]);

                $roleNames = $data['roles'] ?? ['ROLE_USER'];
                $roleIds = collect($roleNames)->map(function ($r) {
                    $name = mb_strtoupper(trim((string)$r), 'UTF-8');
                    $aliases = [
                        'ADMIN' => 'ROLE_ADMIN',
                        'EMPLOYEE' => 'ROLE_EMPLOYEE',
                        'EMPLOYEES' => 'ROLE_EMPLOYEE',
                        'USER' => 'ROLE_USER',
                    ];
                    if (isset($aliases[$name])) $name = $aliases[$name];
                    $role = Role::firstOrCreate(['name' => $name]);
                    return $role->id;
                })->filter()->unique()->values()->all();

                if (!empty($roleIds)) {
                    $user->roles()->sync($roleIds);
                }

                return [$user];
            });

            return response()->json($this->userResponse(User::with('roles')->find($user->id)), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(422, 'Validation failed', $e->errors());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllUsers(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $users = User::with('roles')->orderBy('id', 'asc')->get();
            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllCustomers(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $users = User::whereHas('roles', function ($q) {
                $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_USER', 'USER']);
            })->with('roles')->orderBy('id', 'asc')->get();

            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllEmployees(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $users = User::whereHas('roles', function ($q) {
                $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_EMPLOYEE', 'EMPLOYEE', 'ROLE_EMPLOYEES', 'EMPLOYEES']);
            })->with('roles')->orderBy('id', 'asc')->get();

            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function searchUsers(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $term = (string) $request->query('searchTerm', '');
            $users = User::with('roles')
                ->when($term !== '', function ($q) use ($term) {
                    $q->whereRaw('LOWER(user_name) LIKE ?', ['%' . mb_strtolower($term, 'UTF-8') . '%']);
                })
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function searchCustomers(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $term = (string) $request->query('searchTerm', '');
            $users = User::with('roles')
                ->whereHas('roles', function ($q) {
                    $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_USER', 'USER']);
                })
                ->when($term !== '', function ($q) use ($term) {
                    $q->whereRaw('LOWER(user_name) LIKE ?', ['%' . mb_strtolower($term, 'UTF-8') . '%']);
                })
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function searchEmployees(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $term = (string) $request->query('searchTerm', '');
            $users = User::with('roles')
                ->whereHas('roles', function ($q) {
                    $q->whereIn(DB::raw('UPPER(name)'), ['ROLE_EMPLOYEE', 'EMPLOYEE', 'ROLE_EMPLOYEES', 'EMPLOYEES']);
                })
                ->when($term !== '', function ($q) use ($term) {
                    $q->whereRaw('LOWER(user_name) LIKE ?', ['%' . mb_strtolower($term, 'UTF-8') . '%']);
                })
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($users->map(fn($u) => $this->userResponse($u))->values());
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function updateUser(Request $request)
    {
        try {
            $data = $request->validate([
                'updatedUserId' => ['required','integer','min:1'],
                'userName'      => ['sometimes','string','min:2','max:20', Rule::unique('users','user_name')->ignore($request->input('updatedUserId'))],
                'email'         => ['sometimes','email','max:50', Rule::unique('users','email')->ignore($request->input('updatedUserId'))],
                'roles'         => ['sometimes','array'],
                'roles.*'       => ['string','min:2','max:50'],
            ]);

            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $user = User::with('roles')->findOrFail((int)$data['updatedUserId']);

            if (array_key_exists('userName', $data)) $user->user_name = mb_strtolower(trim($data['userName']), 'UTF-8');
            if (array_key_exists('email', $data)) $user->email = mb_strtolower(trim($data['email']), 'UTF-8');

            DB::transaction(function () use ($user, $data) {
                $user->save();

                if (isset($data['roles'])) {
                    $roleIds = collect($data['roles'])->map(function ($r) {
                        $name = mb_strtoupper(trim($r), 'UTF-8');
                        $aliases = [
                            'ADMIN' => 'ROLE_ADMIN',
                            'EMPLOYEE' => 'ROLE_EMPLOYEE',
                            'EMPLOYEES' => 'ROLE_EMPLOYEE',
                            'USER' => 'ROLE_USER',
                        ];
                        if (isset($aliases[$name])) $name = $aliases[$name];
                        $role = Role::firstOrCreate(['name' => $name]);
                        return $role->id;
                    })->filter()->unique()->values()->all();

                    $user->roles()->sync($roleIds);
                }
            });

            return response()->json($this->userResponse(User::with('roles')->find($user->id)));
        } catch (ValidationException $e) {
            return $this->errorResponse(422, 'Validation failed', $e->errors());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse(404, 'User not found', ['user' => ['User not found.']]);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function deleteUser(int $id)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $user = User::find($id);
            if (!$user) {
                return $this->errorResponse(404, 'User not found', ['user' => ['User not found.']]);
            }

            $user->delete();
            return response()->json(['message' => 'User deleted successfully'], 204);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllBooks(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $books = Book::get();
            if ($books->count() > 0) {
                return BookResource::collection($books);
            }
            return response()->json(['message' => 'No record available'], 200);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getBookById(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $id = $request->query('Id', $request->query('id'));
            if (!$id) {
                return $this->errorResponse(400, 'Missing parameter: Id', ['id' => ['Missing parameter: Id']]);
            }

            $book = Book::find($id);
            if (!$book) {
                return response()->json(null, 404);
            }
            return new BookResource($book);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function createBook(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $validated = Validator::make($request->all(), [
                'title'            => ['required','string','max:255'],
                'author'           => ['required','string','max:255'],
                'description'      => ['required','string','max:1000'],
                'category'         => ['required','string','max:100'],
                'price'            => ['required','numeric','gt:0'],
                'publisher'        => ['required','string','max:255'],
                'publicationDate'  => ['required','date','before_or_equal:today'],
                'language'         => ['required','string','max:100'],
                'readingAge'       => ['required','integer','min:0'],
                'pages'            => ['required','integer','min:1'],
                'dimension'        => ['nullable','string','max:50'],
                'quantity'         => ['sometimes','integer','min:0'],
                'discount'         => ['sometimes','numeric','min:0'],
                'imageUrl'         => ['sometimes','nullable','string','url'],
            ]);

            if ($validated->fails()) {
                return $this->errorResponse(422, 'Validation failed', $validated->messages()->toArray());
            }

            $data = $validated->validated();
            $data['quantity'] = (int) $request->input('quantity', 0);
            $data['discount'] = (float) $request->input('discount', 0.0);

            if (Book::where('title', $data['title'])->exists()) {
                return $this->errorResponse(422, 'Validation failed', ['title' => ['Title already exists']]);
            }

            $payload = [];
            foreach ($data as $k => $v) {
                $payload[Str::snake($k)] = $v;
            }

            $book = Book::create($payload);

            return response()->json([
                'message' => 'Book created successfully',
                'data'    => new BookResource($book),
            ], 201);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function updateBook(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $id = $request->query('id', $request->query('Id'));
            if (!$id) {
                return $this->errorResponse(400, 'Missing parameter: id', ['id' => ['Missing parameter: id']]);
            }

            $book = Book::find($id);
            if (!$book) {
                return $this->errorResponse(404, 'Book not found', ['book' => ['Book not found.']]);
            }

            $validator = Validator::make($request->all(), [
                'title'           => ['sometimes','string','max:255'],
                'author'          => ['sometimes','string','max:255'],
                'description'     => ['sometimes','string','max:1000'],
                'category'        => ['sometimes','string','max:100'],
                'price'           => ['sometimes','numeric','gt:0'],
                'publisher'       => ['sometimes','string','max:255'],
                'publicationDate' => ['sometimes','date','date_format:Y-m-d','before_or_equal:today'],
                'language'        => ['sometimes','string','max:100'],
                'readingAge'      => ['sometimes','integer','min:0'],
                'pages'           => ['sometimes','integer','min:1'],
                'dimension'       => ['sometimes','nullable','string','max:50'],
                'quantity'        => ['sometimes','integer','min:0'],
                'discount'        => ['sometimes','numeric','min:0'],
                'imageUrl'        => ['sometimes','nullable','string','url'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(422, 'Validation failed', $validator->messages()->toArray());
            }

            $data = $validator->validated();

            if (array_key_exists('title', $data)) {
                $newTitle = $data['title'];
                if ($newTitle !== null && $newTitle !== $book->title) {
                    $exists = Book::where('title', $newTitle)->where('id', '!=', $book->id)->exists();
                    if ($exists) {
                        return $this->errorResponse(422, 'Validation failed', ['title' => ['Title already exists']]);
                    }
                }
            }

            $map = [
                'title','author','description','category','price','publisher',
                'publicationDate','language','readingAge','pages','dimension',
                'quantity','discount','imageUrl'
            ];

            foreach ($map as $f) {
                if ($request->has($f)) {
                    $snake = Str::snake($f);
                    $book->{$snake} = $data[$f];
                }
            }

            $book->save();
            return new BookResource($book);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function deleteBook(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $id = $request->query('id', $request->query('Id'));
            if (!$id) {
                return $this->errorResponse(400, 'Missing parameter: id', ['id' => ['Missing parameter: id']]);
            }

            $book = Book::find($id);
            if (!$book) {
                return $this->errorResponse(404, 'Book not found', ['book' => ['Book not found.']]);
            }

            DB::beginTransaction();
            try {
                if (Schema::hasTable('cart_items')) {
                    DB::table('cart_items')->where('book_id', $id)->delete();
                }
                $book->delete();
                DB::commit();
                return response()->json(null, 204);
            } catch (\Throwable $e) {
                DB::rollBack();
                return $this->errorResponse(500, 'Delete failed', ['server' => [$e->getMessage()]]);
            }
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function searchBooks(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $searchTerm = $request->query('searchTerm');
            $sort       = $request->query('sort');
            $page       = (int) $request->query('page', 0);
            $size       = (int) $request->query('size', 12);

            $query = Book::query();

            if ($searchTerm !== null && trim($searchTerm) !== '') {
                $term = mb_strtolower($searchTerm);
                $query->where(function($q) use ($term) {
                    foreach (['title','author','description','category','publisher','language'] as $col) {
                        $q->orWhereRaw('LOWER('.$col.') LIKE ?', ["%{$term}%"]);
                    }
                });
            }

            $filters = collect($request->query())
                ->filter(fn($v,$k) => str_starts_with($k, 'filter_'));

            $types = [
                'id'               => 'int',
                'title'            => 'string',
                'author'           => 'string',
                'description'      => 'string',
                'category'         => 'string',
                'price'            => 'float',
                'publisher'        => 'string',
                'publication_date' => 'date',
                'language'         => 'string',
                'reading_age'      => 'int',
                'pages'            => 'int',
                'dimension'        => 'string',
                'quantity'         => 'int',
                'discount'         => 'float',
            ];

            foreach ($filters as $key => $value) {
                $raw = substr($key, strlen('filter_'));
                $parts = explode('_', $raw, 2);
                $fieldRaw = $parts[0] ?? null;
                $op       = strtolower($parts[1] ?? 'eq');

                $field = Str::snake($fieldRaw ?? '');
                if (!$field || !array_key_exists($field, $types)) {
                    return $this->errorResponse(400, "Unsupported field: {$fieldRaw}");
                }

                try {
                    $typed = match ($types[$field]) {
                        'int'   => (int) $value,
                        'float' => (float) $value,
                        'date'  => Carbon::parse($value)->toDateString(),
                        default => (string) $value,
                    };
                } catch (\Throwable $e) {
                    return $this->errorResponse(400, "Invalid value for {$fieldRaw}: {$value}");
                }

                switch ($op) {
                    case 'eq':  $query->where($field, '=', $typed); break;
                    case 'neq': $query->where($field, '!=', $typed); break;
                    case 'gte': $query->where($field, '>=', $typed); break;
                    case 'lte': $query->where($field, '<=', $typed); break;
                    case 'gt':  $query->where($field, '>',  $typed); break;
                    case 'lt':  $query->where($field, '<',  $typed); break;
                    case 'like':
                        if ($types[$field] !== 'string') {
                            return $this->errorResponse(400, "Operator like only applies to string: {$fieldRaw}");
                        }
                        $query->where($field, 'LIKE', "%{$typed}%");
                        break;
                    default:
                        return $this->errorResponse(400, "Unsupported operator: {$op}");
                }
            }

            if ($sort) {
                $parts = array_map('trim', explode(',', $sort));
                if (count($parts) !== 2) {
                    return $this->errorResponse(400, 'Invalid sort parameter');
                }
                [$sField, $dir] = $parts;
                $sField = Str::snake($sField);
                $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
                if (!array_key_exists($sField, $types)) {
                    return $this->errorResponse(400, "Cannot sort by field: {$parts[0]}");
                }
                $query->orderBy($sField, $dir);
            }

            $paginator = $query->paginate($size, ['*'], 'page', $page + 1);
            return BookResource::collection($paginator);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function searchBookTitle(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $term = $request->query('term');
            if ($term === null || trim($term) === '') {
                return BookResource::collection(Book::all());
            }

            $term = mb_strtolower($term);
            $books = Book::whereRaw('LOWER(title) LIKE ?', ["%{$term}%"])->get();
            return BookResource::collection($books);
        } catch (QueryException $e) {
            return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
        } catch (Exception $e) {
            return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
        }
    }

    public function getAllOrders(Request $request)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
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

    public function getOrderById(Request $request, int $id)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
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

    public function getOrderByCode(Request $request, string $orderCode)
    {
        try {
            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $order = Order::with(['orderItems.book', 'address', 'payment'])
                ->where('order_code', $orderCode)
                ->firstOrFail();

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

            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $status = strtoupper(trim($data['orderStatus']));
            if (!in_array($status, ['ACCEPTED','SHIPPING','COMPLETED','CANCELLED'], true)) {
                return $this->errorResponse(400, 'Invalid order status', ['orderStatus' => ['Allowed: ACCEPTED, SHIPPING, COMPLETED, CANCELLED']]);
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

    public function updatePaymentStatus(Request $request, int $orderId)
    {
        try {
            $data = $request->validate([
                'paymentStatus' => ['required','string'],
            ]);

            $current = auth('api')->user();
            if (!$this->currentUserIsAdmin($current->id)) {
                return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
            }

            $status = strtoupper(trim($data['paymentStatus']));
            if (!in_array($status, ['PENDING','PAID','FAILED','SUCCESS'], true)) {
                return $this->errorResponse(400, 'Invalid payment status', ['paymentStatus' => ['Allowed: PENDING, PAID, FAILED, SUCCESS']]);
            }

            $order = Order::find($orderId);
            if (!$order) {
                return $this->errorResponse(404, 'Order not found', ['order' => ['Order not found.']]);
            }

            $order->payment_status = $status;
            if (in_array($status, ['PAID','SUCCESS'], true)) {
                if (!$order->paid_at) {
                    $order->paid_at = now();
                }
            } else {
                $order->paid_at = null;
            }
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

    private function currentUserIsAdmin(int $userId): bool
    {
        $roles = DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $userId)
            ->pluck('roles.name')
            ->map(fn ($r) => mb_strtoupper(trim($r), 'UTF-8'))
            ->toArray();

        return in_array('ADMIN', $roles, true)
            || in_array('ROLE_ADMIN', $roles, true);
    }

    private function userResponse(User $u): array
    {
        return [
            'id'       => $u->id,
            'userName' => $u->user_name,
            'email'    => $u->email,
            'roles'    => $u->roles?->pluck('name')->values()->all() ?? [],
        ];
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
            'orderCode'      => $order->order_code,
            'email'          => $order->email,
            'orderDate'      => $order->order_date,
            'createdAt'      => optional($order->created_at)?->toIso8601String(),
            'paidAt'         => optional($order->paid_at)?->toIso8601String(),
            'totalAmount'    => $order->total_amount,
            'orderStatus'    => $order->order_status,
            'paymentStatus'  => $order->payment_status,
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
    
    public function searchOrdersByCode(Request $request)
{
    try {
        $current = auth('api')->user();
        if (!$this->currentUserIsAdmin($current->id)) {
            return $this->errorResponse(403, 'Access denied', ['auth' => ['Admin role required.']]);
        }

        $term = (string) $request->query('searchTerm', '');
        $term = trim($term);

        $query = Order::with(['orderItems.book', 'address', 'payment'])
            ->orderByDesc('order_date');

        if ($term !== '') {
            $termLower = mb_strtolower($term, 'UTF-8');
            $query->whereRaw('LOWER(order_code) LIKE ?', ['%' . $termLower . '%']);
        }

        $orders = $query->get();

        $list = [];
        foreach ($orders as $o) {
            $list[] = $this->orderResponse($o);
        }

        return response()->json($list);
    } catch (\Illuminate\Database\QueryException $e) {
        return $this->errorResponse(500, 'Database error', ['database' => [$e->getMessage()]]);
    } catch (\Exception $e) {
        return $this->errorResponse(500, 'Server error', ['server' => [$e->getMessage()]]);
    }
}

    private function errorResponse(int $status, string $message, array $errors = [])
    {
        return response()->json([
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
