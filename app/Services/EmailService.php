<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $domain;

    public function __construct()
    {
        $this->baseUrl = config('services.email_api.base_url', 'https://email.absterco.com');
        $this->apiKey = config('services.email_api.api_key');
        $this->domain = config('services.email_api.domain', 'menuvire.com');
    }

    /**
     * Send an email using a template
     */
    public function send(string $to, string $template, array $data = []): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/email/send", [
                'domain' => $this->domain,
                'to' => $to,
                'template' => $template,
                'data' => $data,
            ]);

            if ($response->successful()) {
                Log::info("Email sent successfully", [
                    'to' => $to,
                    'template' => $template,
                    'response' => $response->json(),
                ]);
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'data' => $response->json(),
                ];
            }

            Log::error("Failed to send email", [
                'to' => $to,
                'template' => $template,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Failed to send email',
                'error' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error("Email service exception", [
                'to' => $to,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Email service error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send welcome email
     */
    public function sendWelcome(string $to, string $userName, string $verificationLink = null): array
    {
        return $this->send($to, 'welcome', [
            'user_name' => $userName,
            'platform_name' => 'MenuVibe',
            'verification_link' => $verificationLink ?? config('app.frontend_url') . '/verify',
        ]);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $to, string $userName, string $resetLink, string $expiresAt = '1 hour'): array
    {
        return $this->send($to, 'password-reset', [
            'user_name' => $userName,
            'platform_name' => 'MenuVibe',
            'reset_link' => $resetLink,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Send OTP email
     */
    public function sendOtp(string $to, string $userName, string $otpCode, string $expiresIn = '10 minutes'): array
    {
        return $this->send($to, 'otp', [
            'user_name' => $userName,
            'platform_name' => 'MenuVibe',
            'otp_code' => $otpCode,
            'expires_in' => $expiresIn,
        ]);
    }

    /**
     * Send franchise credentials email
     */
    public function sendFranchiseCredentials(
        string $to,
        string $userName,
        string $franchiseName,
        string $email,
        string $password,
        string $loginLink
    ): array {
        return $this->send($to, 'franchise-credentials', [
            'user_name' => $userName,
            'franchise_name' => $franchiseName,
            'platform_name' => 'MenuVibe',
            'email' => $email,
            'password' => $password,
            'login_link' => $loginLink,
        ]);
    }

    /**
     * Send franchise invitation email
     */
    public function sendFranchiseInvitation(
        string $to,
        string $inviteeName,
        string $franchiseName,
        string $role,
        string $invitedBy,
        string $acceptLink
    ): array {
        return $this->send($to, 'franchise-invitation', [
            'invitee_name' => $inviteeName,
            'franchise_name' => $franchiseName,
            'platform_name' => 'MenuVibe',
            'role' => $role,
            'invited_by' => $invitedBy,
            'accept_link' => $acceptLink,
        ]);
    }

    /**
     * Send invoice notification email
     */
    public function sendInvoiceNotification(
        string $to,
        string $customerName,
        string $invoiceNumber,
        float $amount,
        string $dueDate,
        string $paymentLink
    ): array {
        return $this->send($to, 'invoice-notification', [
            'customer_name' => $customerName,
            'invoice_number' => $invoiceNumber,
            'amount' => $amount,
            'due_date' => $dueDate,
            'payment_link' => $paymentLink,
        ]);
    }

    /**
     * Get email sending statistics
     */
    public function getStats(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->get("{$this->baseUrl}/api/email/stats");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get stats',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List available templates
     */
    public function listTemplates(string $category = null): array
    {
        try {
            $url = "{$this->baseUrl}/api/email/templates";
            if ($category) {
                $url .= "?category={$category}";
            }

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to list templates',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
