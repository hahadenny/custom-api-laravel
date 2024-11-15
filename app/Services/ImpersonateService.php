<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Foundation\Application;

class ImpersonateService
{
    const SESSION_KEY = 'impersonator_id';

    private $auth;

    public function __construct(Application $app)
    {
        $this->auth = $app->get('auth');
    }

    public function take(User $from, User $to)
    {
        if ($this->isImpersonating()) {
            abort('403', 'This user is already impersonating someone.');
        }
        if ($from->id === $to->id) {
            abort('403', 'It is not possible to impersonate self.');
        }
        if (!$from->isSuperAdmin()) {
            abort('403', 'Permission denied.');
        }
        $this->auth->logout();
        $this->setImpersonatorId($from->id);
        $token = $this->auth->login($to);
        $this->auth->setToken($token);
        return $token;
    }

    public function leave()
    {
        if (!$this->isImpersonating()) {
            abort('403', 'This user is not impersonating someone.');
        }
        $to = User::findOrFail($this->getImpersonatorId());
        $this->auth->logout();
        $this->setImpersonatorId(null);
        $token = $this->auth->login($to);
        $this->auth->setToken($token);
        return $token;
    }

    public function isImpersonating() : bool
    {
        return !empty($this->getImpersonatorId());
    }

    protected function getImpersonatorId()
    {
        return $this->auth->parseToken()->getPayLoad()->get(self::SESSION_KEY);
    }

    protected function setImpersonatorId($key)
    {
        $this->auth->customClaims([self::SESSION_KEY => $key]);
    }

}
