<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait MakesMongoModels
{
    protected function makeMongoUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = $this->makeMongoModel(User::class, array_merge([
            '_id' => 'test-user-id',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ], $attributes));

        return $user;
    }

    /**
     * Build a Mongo-backed Eloquent model without persisting it.
     *
     * This keeps feature tests independent from a running MongoDB server while
     * preserving Mongo-style identifiers such as _id/getKey().
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    protected function makeMongoModel(string $modelClass, array $attributes = []): Model
    {
        /** @var TModel $model */
        $model = new $modelClass();
        $model->forceFill($attributes);
        $model->exists = true;

        return $model;
    }
}
