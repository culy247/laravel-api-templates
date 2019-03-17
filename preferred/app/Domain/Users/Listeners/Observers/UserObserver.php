<?php

namespace Preferred\Domain\Users\Listeners\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Neves\Events\Contracts\TransactionalEvent;
use Preferred\Domain\Users\Entities\User;
use Ramsey\Uuid\Uuid;

class UserObserver implements TransactionalEvent
{
    public function creating(Model $model)
    {
        $model->id = Uuid::uuid4();
    }

    public function updated(User $user)
    {
        if ($user->is_active) {
            Cache::put($user->id, $user, 3600);
        } else {
            Cache::forget($user->id);
        }

        Cache::tags('users:' . $user->id)->flush();
        Cache::tags('users')->flush();
    }
}
