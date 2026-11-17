<?php

namespace App\Livewire\Concerns;

use App\Models\Department;
use App\Models\Tenant;
use App\Services\TelegramBotService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait ManagesTelegramPairing
{
    public bool $showTelegramModal = false;

    public ?string $telegramPairingToken = null;

    public ?string $telegramPairingUrl = null;

    public ?int $selectedDepartmentTelegramId = null;

    public ?string $departmentTelegramToken = null;

    /**
     * Open the personal Telegram pairing modal.
     */
    public function openTelegramModal(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->showTelegramModal = true;
        $this->refreshTelegramPairingState();
    }

    /**
     * Close the Telegram pairing modal.
     */
    public function closeTelegramModal(): void
    {
        $this->showTelegramModal = false;
        $this->selectedDepartmentTelegramId = null;
        $this->departmentTelegramToken = null;
    }

    /**
     * Refresh pairing token and deep-link URL.
     */
    public function refreshTelegramPairingState(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $tenant = Tenant::find($this->tenantId) ?? $user->tenant;
        $botService = app(TelegramBotService::class);
        $botUsername = $botService->resolveUsername($tenant) ?? 'TantouBot';

        if (empty($user->telegram_chat_id)) {
            $token = $user->telegram_link_token ?: Str::random(32);
            if ($user->telegram_link_token !== $token) {
                $user->update(['telegram_link_token' => $token]);
            }
            $this->telegramPairingToken = $token;
            $this->telegramPairingUrl = "https://t.me/{$botUsername}?start=usr_{$token}";
        } else {
            $this->telegramPairingToken = null;
            $this->telegramPairingUrl = null;
        }
    }

    /**
     * Generate a new pairing token for current user.
     */
    public function generateTelegramPairingToken(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $token = Str::random(32);
        $user->update(['telegram_link_token' => $token]);
        $this->refreshTelegramPairingState();
    }

    /**
     * Unlink Telegram account from current user.
     */
    public function unlinkTelegram(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->update([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_link_token' => null,
        ]);

        $this->refreshTelegramPairingState();
    }

    /**
     * Toggle Telegram notifications for current user.
     */
    public function toggleTelegramNotifications(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->update([
            'telegram_notifications_enabled' => ! $user->telegram_notifications_enabled,
        ]);
    }

    /**
     * Generate a Telegram group linking token for a Department (Admin only).
     */
    public function generateDepartmentTelegramToken(int $departmentId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403);
        }

        $dept = Department::where('tenant_id', $this->tenantId)->findOrFail($departmentId);
        $token = Str::random(32);
        $dept->update(['telegram_link_token' => $token]);

        $this->selectedDepartmentTelegramId = $dept->id;
        $this->departmentTelegramToken = $token;
    }

    /**
     * Unlink Telegram group from a Department (Admin only).
     */
    public function unlinkDepartmentTelegram(int $departmentId): void
    {
        $currentUser = Auth::user();
        if (! $currentUser || ! $currentUser->isAdmin()) {
            abort(403);
        }

        $dept = Department::where('tenant_id', $this->tenantId)->findOrFail($departmentId);
        $dept->update([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
        ]);

        if ($this->selectedDepartmentTelegramId === $dept->id) {
            $this->departmentTelegramToken = null;
        }
    }
}
