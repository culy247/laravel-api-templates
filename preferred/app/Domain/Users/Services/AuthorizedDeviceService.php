<?php

namespace Preferred\Domain\Users\Services;

use Illuminate\Support\Facades\Notification;
use Preferred\Domain\Users\Contracts\AuthorizedDeviceRepository;
use Preferred\Domain\Users\Entities\User;
use Preferred\Domain\Users\Notifications\AuthorizeDeviceNotification;
use Ramsey\Uuid\Uuid;

class AuthorizedDeviceService
{
    /**
     * @var AuthorizedDeviceRepository
     */
    private $authorizedDeviceRepository;

    public function __construct(AuthorizedDeviceRepository $authorizedDeviceRepository)
    {
        $this->authorizedDeviceRepository = $authorizedDeviceRepository;
    }

    public function store(User $user, array $data)
    {
        $device = $this->authorizedDeviceRepository->findDeviceByCriteria($data);

        if (empty($device)) {
            $doesNotHaveAuthorizedAnyDeviceYet = $this->authorizedDeviceRepository
                ->doesNotHaveAuthorizedAnyDeviceYet($data['user_id']);

            if ($doesNotHaveAuthorizedAnyDeviceYet) {
                $this->authorizedDeviceRepository->store([
                    'device'              => $data['device'],
                    'platform'            => $data['platform'],
                    'platform_version'    => $data['platform_version'],
                    'browser'             => $data['browser'],
                    'browser_version'     => $data['browser_version'],
                    'city'                => $data['city'],
                    'country_name'        => $data['country_name'],
                    'authorization_token' => Uuid::uuid4()->toString(),
                    'authorized_at'       => now()->format('Y-m-d H:i:s.u'),
                    'user_id'             => $data['user_id'],
                ]);

                return true;
            }
        }

        if (!empty($device->authorized_at)) {
            return true;
        }

        return $this->storeAndAskAuthorizationForNewDevice($user, $data);
    }

    private function storeAndAskAuthorizationForNewDevice(User $user, array $data)
    {
        $device = $this->authorizedDeviceRepository->store([
            'device'              => $data['device'],
            'platform'            => $data['platform'],
            'platform_version'    => $data['platform_version'],
            'browser'             => $data['browser'],
            'browser_version'     => $data['browser_version'],
            'city'                => $data['city'],
            'country_name'        => $data['country_name'],
            'authorization_token' => Uuid::uuid4()->toString(),
            'authorized_at'       => null,
            'user_id'             => $data['user_id'],
        ]);

        $device = $device->toArray();
        $device['ip'] = $data['ip'];

        Notification::send($user, new AuthorizeDeviceNotification($device));

        $message = __(
            'We sent a confirmation email to :email. Please follow the instructions to authorize this new ' .
            'device/location.',
            ['email' => $user->email]
        );

        return [
            'error'   => true,
            'message' => $message,
        ];
    }
}
