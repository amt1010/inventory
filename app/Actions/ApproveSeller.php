<?php

namespace App\Actions;

use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ApproveSeller
{
    /**
     * @return array<int, string>
     */
    public function blockingReasons(Seller $seller): array
    {
        $reasons = [];

        $requiredFields = [
            'company_name' => 'Company Name',
            'contact_person' => 'Contact Person',
            'phone' => 'Phone',
            'business_address' => 'Business Address',
            'gst_number' => 'GST Number',
            'manufacturing_activity' => 'Manufacturing Activity',
            'availability_hours' => 'Availability Hours',
        ];

        foreach ($requiredFields as $field => $label) {
            if ($seller->{$field} === Seller::PLACEHOLDER) {
                $reasons[] = "{$label} still needs to be filled in.";
            }
        }

        if ($seller->email === Seller::PLACEHOLDER) {
            $reasons[] = 'Email still needs to be filled in.';
        } elseif (str_contains($seller->email, ',')) {
            $reasons[] = 'Email must be a single address, not a comma-separated list.';
        } elseif (! filter_var($seller->email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email is not a valid address.';
        } elseif (Seller::query()->where('email', $seller->email)->where('id', '!=', $seller->id)->exists()) {
            $reasons[] = 'Email is already used by another seller.';
        }

        if (
            filled($seller->gst_number)
            && $seller->gst_number !== Seller::PLACEHOLDER
            && Seller::query()->where('gst_number', $seller->gst_number)->where('id', '!=', $seller->id)->exists()
        ) {
            $reasons[] = 'GST Number is already used by another seller.';
        }

        return $reasons;
    }

    /**
     * @return array<int, string>
     */
    public function approve(Seller $seller, Staff $staff): array
    {
        $reasons = $this->blockingReasons($seller);

        if ($reasons !== []) {
            return $reasons;
        }

        $needsActivationLink = $seller->created_by === 'admin_bulk_upload' && is_null($seller->password_set_at);

        $seller->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $staff->id,
        ]);

        try {
            Mail::to($seller->email)->send(new SellerApproved(
                $seller,
                $needsActivationLink
                    ? URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id])
                    : null,
            ));
        } catch (\Throwable $exception) {
            Log::error('Failed to queue seller approval email.', [
                'seller_id' => $seller->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [];
    }
}
