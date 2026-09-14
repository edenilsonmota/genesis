<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /** @param array{name: string, cpf: ?string, email: ?string, phone: ?string, remove_profile_photo?: bool} $attributes */
    public function updateDetails(User $user, array $attributes, ?UploadedFile $profilePhoto = null): User
    {
        $newPhotoPath = $profilePhoto?->store('profile-photos', 'public');
        $previousPhotoPath = $user->profile_photo_path;

        try {
            $updated = DB::transaction(function () use ($user, $attributes, $newPhotoPath): User {
                $lockedUser = User::query()->lockForUpdate()->with('member')->findOrFail($user->id);
                $photoPath = $newPhotoPath ?? ($attributes['remove_profile_photo'] ?? false ? null : $lockedUser->profile_photo_path);

                if ($lockedUser->member instanceof Member) {
                    $member = Member::query()->lockForUpdate()->findOrFail($lockedUser->member_id);
                    $member->update([
                        'name' => $attributes['name'],
                        'cpf' => $attributes['cpf'],
                        'email' => $attributes['email'],
                        'phone' => $attributes['phone'],
                    ]);
                    $lockedUser->update([
                        'display_name' => $member->name,
                        'profile_photo_path' => $photoPath,
                    ]);
                } else {
                    $lockedUser->update([
                        'display_name' => $attributes['name'],
                        'cpf' => $attributes['cpf'],
                        'email' => $attributes['email'],
                        'phone' => $attributes['phone'],
                        'profile_photo_path' => $photoPath,
                    ]);
                }

                return $lockedUser->refresh();
            });
        } catch (\Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($previousPhotoPath !== null && $previousPhotoPath !== $updated->profile_photo_path) {
            Storage::disk('public')->delete($previousPhotoPath);
        }

        return $updated;
    }

    public function updateUsername(User $user, string $username): User
    {
        return DB::transaction(function () use ($user, $username): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->update(['username' => $username]);

            return $lockedUser->refresh();
        });
    }

    public function updatePassword(User $user, string $password): User
    {
        return DB::transaction(function () use ($user, $password): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $lockedUser->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => false,
            ])->save();

            return $lockedUser->refresh();
        });
    }
}
