<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $key => $definition) {
            $existing = EmailTemplate::where('key', $key)->first();

            if ($existing) {
                continue;
            }

            EmailTemplate::create(array_merge($definition, [
                'key' => $key,
                'is_system' => true,
                'draft_subject' => $definition['subject'],
                'draft_body' => $definition['body'],
                'draft_default_cc' => null,
                'draft_default_bcc' => null,
                'default_cc' => null,
                'default_bcc' => null,
            ]));
        }
    }

    /**
     * @return array<string, array{label: string, subject: string, body: string}>
     */
    private function templates(): array
    {
        return [
            'product_listing_live' => [
                'label' => 'Product Listing Live',
                'subject' => 'Your listing is now live: {{product_name}}',
                'body' => <<<'HTML'
<h1>Your listing is live</h1>
<p><strong>{{product_name}}</strong> is now published and visible to buyers on the catalog.</p>
<p><a href="{{product_url}}">View your live listing</a></p>
HTML,
            ],
            'quote_request_confirmation' => [
                'label' => 'Quote Request Confirmation (Buyer)',
                'subject' => 'Your Quote Request {{quote_number}} Has Been Received',
                'body' => <<<'HTML'
<h1>Thank you, {{first_name}}!</h1>
<p>We've received your quote request. Your reference number is:</p>
<p style="font-size: 1.5em; font-weight: bold;">{{quote_number}}</p>
<p>Please quote this number in any follow-up correspondence about this enquiry.</p>
{{#product_name}}<p><strong>Product:</strong> {{product_name}}</p>{{/product_name}}
<p>Our team will be in touch shortly.</p>
HTML,
            ],
            'quote_request_received' => [
                'label' => 'Quote Request Received (Staff Notification)',
                'subject' => 'New Quote Request from {{full_name}}',
                'body' => <<<'HTML'
<h1>New Quote Request</h1>
<p><strong>Reason:</strong> {{reason}}</p>
<p><strong>Name:</strong> {{full_name}}</p>
<p><strong>Email:</strong> {{email}}</p>
<p><strong>Phone:</strong> {{phone}}</p>
<p><strong>Company:</strong> {{company}}</p>
{{#product_name}}<p><strong>Product:</strong> {{product_name}}</p>{{product_thumbnail_html}}<p><a href="{{product_url}}">View Product Page</a></p>{{/product_name}}
{{#message_text}}<p><strong>Message:</strong></p><p>{{message_text}}</p>{{/message_text}}
<p><a href="{{admin_url}}">View in the CMS</a></p>
HTML,
            ],
            'seller_activation_admin_created' => [
                'label' => 'Seller Activation (Admin-Created)',
                'subject' => 'Activate your seller account',
                'body' => <<<'HTML'
<h1>Activate your seller account</h1>
<p>An administrator has created a seller account for {{company_name}}. Click below to set your password and activate your account.</p>
<p><a href="{{activation_url}}">Activate Account</a></p>
HTML,
            ],
            'seller_activation_self_registered' => [
                'label' => 'Seller Activation (Self-Registered)',
                'subject' => 'Activate your seller account',
                'body' => <<<'HTML'
<h1>Activate your seller account</h1>
<p>Thanks for registering {{company_name}}. Click below to verify your email address.</p>
<p><a href="{{activation_url}}">Activate Account</a></p>
HTML,
            ],
            'seller_approved' => [
                'label' => 'Seller Approved',
                'subject' => "Your seller account has been approved",
                'body' => <<<'HTML'
<h1>You're approved!</h1>
<p>Congratulations — {{company_name}}'s seller account has been approved. You can now log in and start listing products.</p>
{{#activation_url}}<p>Before you can log in, set your password: <a href="{{activation_url}}">Set Your Password</a></p>{{/activation_url}}
HTML,
            ],
            'seller_rejected' => [
                'label' => 'Seller Rejected',
                'subject' => 'Update on your seller application',
                'body' => <<<'HTML'
<h1>Update on your application</h1>
<p>Thank you for applying to become a seller. Unfortunately, we're unable to approve {{company_name}}'s application at this time.</p>
{{#rejection_reason}}<p><strong>Reason:</strong> {{rejection_reason}}</p>{{/rejection_reason}}
HTML,
            ],
            'staff_invitation' => [
                'label' => 'Staff Invitation',
                'subject' => 'Your admin panel login',
                'body' => <<<'HTML'
<h1>Welcome to the admin panel</h1>
<p>An account has been created for you, {{staff_name}}.</p>
<p>Log in at <a href="{{login_url}}">{{login_url}}</a> using this temporary password:</p>
<p><strong>{{temporary_password}}</strong></p>
<p>You'll be asked to set a new password the first time you log in.</p>
HTML,
            ],
            'staff_password_reset' => [
                'label' => 'Staff Password Reset',
                'subject' => 'Reset your admin panel password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi {{staff_name}}, click below to set a new password for your admin panel account.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
            'seller_password_reset' => [
                'label' => 'Seller Password Reset',
                'subject' => 'Reset your seller account password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi, click below to set a new password for {{company_name}}'s seller account.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
            'buyer_password_reset' => [
                'label' => 'Buyer Password Reset',
                'subject' => 'Reset your password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi {{name}}, click below to set a new password.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
        ];
    }
}
