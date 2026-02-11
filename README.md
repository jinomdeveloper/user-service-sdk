# Jinom User Service SDK

Laravel package untuk auto-sync user ke User Service setelah login/register via Keycloak.

## Features

- **Token Management**: Store, refresh, dan manage Keycloak tokens dengan auto-refresh
- **User Sync**: Otomatis sync user ke User Service setelah Keycloak login
- **Async Queue**: Support async sync via Laravel Queue
- **Flexible API**: Facade, trait, dan DI support

## Installation

```bash
composer require jinom/user-service-sdk
```

Publish config file:

```bash
php artisan vendor:publish --tag="user-service-sdk-config"
```

## Configuration

Tambahkan environment variables:

```env
# Keycloak
KEYCLOAK_BASE_URL=https://keycloak.example.com
KEYCLOAK_REALM=your-realm
KEYCLOAK_CLIENT_ID=your-client-id
KEYCLOAK_CLIENT_SECRET=your-client-secret

# User Service
USER_SERVICE_URL=http://user-service:3000

# Sync Options
USER_SERVICE_SYNC_ENABLED=true
USER_SERVICE_SYNC_QUEUE=default
```

## Usage

### Basic Usage dengan Trait

```php
use Jinom\UserServiceSdk\Traits\SyncsWithUserService;

class LoginController extends Controller
{
    use SyncsWithUserService;

    public function handleKeycloakCallback()
    {
        $keycloakUser = Socialite::driver('keycloak')->user();

        // Create or update local user
        $user = User::updateOrCreate(
            ['email' => $keycloakUser->email],
            [
                'name' => $keycloakUser->name,
                'keycloak_id' => $keycloakUser->id,
            ]
        );

        // Sync to User Service (async via queue)
        $this->syncToUserService($keycloakUser, $user, [
            'access_token' => $keycloakUser->token,
            'refresh_token' => $keycloakUser->refreshToken,
            'expires_in' => $keycloakUser->expiresIn,
        ]);

        Auth::login($user);

        return redirect()->intended('/dashboard');
    }
}
```

### Using Facade

```php
use Jinom\UserServiceSdk\Facades\UserServiceSdk;

// Store tokens
UserServiceSdk::storeTokens($userId, [
    'access_token' => $token,
    'refresh_token' => $refreshToken,
    'expires_in' => 300,
]);

// Get valid token (auto-refresh if expired)
$token = UserServiceSdk::getValidToken($userId);

// Sync user
UserServiceSdk::syncUser($keycloakUser, $localUser, $tokenData);

// Direct API calls to User Service
$user = UserServiceSdk::findByKeycloakId($localUserId, $keycloakSub);
UserServiceSdk::createOrUpdateUser($localUserId, $keycloakSub, $userData);
```

### Using Dependency Injection

```php
use Jinom\UserServiceSdk\UserServiceSdk;

class UserController extends Controller
{
    public function __construct(
        protected UserServiceSdk $userServiceSdk
    ) {}

    public function syncUser(Request $request)
    {
        $this->userServiceSdk->syncUser(
            $request->keycloakUser,
            $request->user(),
            $request->tokenData
        );
    }
}
```

## Events

Package akan dispatch events untuk tracking sync status:

```php
use Jinom\UserServiceSdk\Events\UserSyncSucceeded;
use Jinom\UserServiceSdk\Events\UserSyncFailed;

// Di EventServiceProvider
protected $listen = [
    UserSyncSucceeded::class => [
        LogSuccessfulSync::class,
    ],
    UserSyncFailed::class => [
        NotifyAdminOnSyncFailure::class,
    ],
];
```

### UserSyncSucceeded

```php
class LogSuccessfulSync
{
    public function handle(UserSyncSucceeded $event)
    {
        Log::info('User synced', [
            'local_user_id' => $event->localUserId,
            'keycloak_sub' => $event->keycloakSub,
            'response' => $event->userServiceResponse,
        ]);
    }
}
```

### UserSyncFailed

```php
class NotifyAdminOnSyncFailure
{
    public function handle(UserSyncFailed $event)
    {
        Log::error('User sync failed', [
            'local_user_id' => $event->localUserId,
            'keycloak_sub' => $event->keycloakSub,
            'error' => $event->errorMessage,
            'code' => $event->errorCode,
        ]);
    }
}
```

## Field Mapping

Konfigurasi field mapping di config file:

```php
'field_mapping' => [
    // user_service_field => keycloak_field
    'id' => 'sub',
    'username' => 'preferred_username',
    'email' => 'email',
    'first_name' => 'given_name',
    'last_name' => 'family_name',
    'email_verified' => 'email_verified',
],
```

## Token Management

### Manual Token Operations

```php
use Jinom\KeycloakSdk\Facades\KeycloakSdk;

// Store tokens
KeycloakSdk::storeTokens($userId, [
    'access_token' => $token,
    'refresh_token' => $refreshToken,
    'expires_in' => 300,
    'keycloak_id' => $keycloakSub,
]);

// Get valid token (auto-refresh)
$token = UserServiceSdk::getValidToken($userId);

// Get all token data
$tokenData = UserServiceSdk::getTokenData($userId);

// Check if has valid tokens
if (UserServiceSdk::hasValidTokens($userId)) {
    // ...
}

// Clear tokens (on logout)
UserServiceSdk::clearTokens($userId);

// Introspect token
$tokenInfo = UserServiceSdk::introspectToken($token);
```

## User Service Client

Direct API calls ke User Service:

```php
use Jinom\UserServiceSdk\Facades\UserServiceSdk;

// Create user
$result = UserServiceSdk::createUser($localUserId, [
    'id' => $keycloakSub,
    'username' => 'john_doe',
    'email' => 'john@example.com',
]);

// Update user
$result = UserServiceSdk::updateUser($localUserId, $userServiceId, [
    'email' => 'newemail@example.com',
]);

// Find by Keycloak ID
$user = UserServiceSdk::findByKeycloakId($localUserId, $keycloakSub);

// Check if exists
if (UserServiceSdk::userExists($localUserId, $keycloakSub)) {
    // ...
}

// Upsert (create or update)
$result = UserServiceSdk::createOrUpdateUser($localUserId, $keycloakSub, $userData);

// Delete user
UserServiceSdk::deleteUser($localUserId, $userServiceId);
```

## Logout Integration

```php
class LogoutController extends Controller
{
    use SyncsWithUserService;

    public function logout(Request $request)
    {
        $userId = $request->user()->id;

        // Clear Keycloak tokens
        $this->clearKeycloakTokens($userId);

        Auth::logout();

        return redirect('/');
    }
}
```

## Queue Configuration

Untuk async sync, pastikan queue worker berjalan:

```bash
php artisan queue:work --queue=default
```

Atau konfigurasi queue tertentu:

```env
USER_SERVICE_SYNC_QUEUE=user-sync
```

```bash
php artisan queue:work --queue=user-sync
```

## Error Handling

```php
use Jinom\UserServiceSdk\Exceptions\TokenRefreshException;
use Jinom\UserServiceSdk\Exceptions\UserServiceException;

try {
    UserServiceSdk::syncUserDirectly($userId, $keycloakSub, $userData);
} catch (TokenRefreshException $e) {
    // Token refresh failed - user needs to re-login
    Log::error('Token refresh failed', ['error' => $e->getMessage()]);
} catch (UserServiceException $e) {
    // User service API error
    Log::error('User service error', [
        'status' => $e->getCode(),
        'message' => $e->getMessage(),
    ]);
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
