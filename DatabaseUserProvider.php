<?php

namespace QuantaForge\Auth;

use Closure;
use QuantaForge\Contracts\Auth\Authenticatable as UserContract;
use QuantaForge\Contracts\Auth\UserProvider;
use QuantaForge\Contracts\Hashing\Hasher as HasherContract;
use QuantaForge\Contracts\Support\Arrayable;
use QuantaForge\Database\ConnectionInterface;

class DatabaseUserProvider implements UserProvider
{
    /**
     * The active database connection.
     *
     * @var \QuantaForge\Database\ConnectionInterface
     */
    protected $connection;

    /**
     * The hasher implementation.
     *
     * @var \QuantaForge\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The table containing the users.
     *
     * @var string
     */
    protected $table;

    /**
     * Create a new database user provider.
     *
     * @param  \QuantaForge\Database\ConnectionInterface  $connection
     * @param  \QuantaForge\Contracts\Hashing\Hasher  $hasher
     * @param  string  $table
     * @return void
     */
    public function __construct(ConnectionInterface $connection, HasherContract $hasher, $table)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \QuantaForge\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        $user = $this->connection->table($this->table)->find($identifier);

        return $this->getGenericUser($user);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \QuantaForge\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $user = $this->getGenericUser(
            $this->connection->table($this->table)->find($identifier)
        );

        return $user && $user->getRememberToken() && hash_equals($user->getRememberToken(), $token)
                    ? $user : null;
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \QuantaForge\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(UserContract $user, $token)
    {
        $this->connection->table($this->table)
                ->where($user->getAuthIdentifierName(), $user->getAuthIdentifier())
                ->update([$user->getRememberTokenName() => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \QuantaForge\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return;
        }

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // generic "user" object that will be utilized by the Guard instances.
        $query = $this->connection->table($this->table);

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        // Now we are ready to execute the query to see if we have a user matching
        // the given credentials. If not, we will just return null and indicate
        // that there are no matching users from the given credential arrays.
        $user = $query->first();

        return $this->getGenericUser($user);
    }

    /**
     * Get the generic user.
     *
     * @param  mixed  $user
     * @return \QuantaForge\Auth\GenericUser|null
     */
    protected function getGenericUser($user)
    {
        if (! is_null($user)) {
            return new GenericUser((array) $user);
        }
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \QuantaForge\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        return $this->hasher->check(
            $credentials['password'], $user->getAuthPassword()
        );
    }
}
