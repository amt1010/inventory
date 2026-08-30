<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSellerRegistrationRequest;
use App\Mail\SellerActivationMail;
use App\Models\Page;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('seller.register', [
            'termsPage' => Page::query()->where('slug', 'terms-and-conditions')->where('status', 'published')->first(),
            'clerkIdentity' => session('seller_clerk_identity'),
        ]);
    }

    public function store(StoreSellerRegistrationRequest $request): RedirectResponse
    {
        $clerkIdentity = session('seller_clerk_identity');

        if ($clerkIdentity && Seller::where('email', $clerkIdentity['email'])->exists()) {
            return back()->withErrors(['email' => 'A seller account already exists for this email.'])->withInput();
        }

        $seller = Seller::create([
            'company_name' => $request->validated('company_name'),
            'contact_person' => $request->validated('contact_person'),
            'phone' => $request->validated('phone'),
            'email' => $clerkIdentity['email'] ?? $request->validated('email'),
            'business_address' => $request->validated('business_address'),
            'gst_number' => $request->validated('gst_number'),
            'manufacturing_activity' => $request->validated('manufacturing_activity'),
            'availability_hours' => $request->validated('availability_hours'),
            'password' => $clerkIdentity ? null : Hash::make($request->validated('password')),
            'clerk_user_id' => $clerkIdentity['id'] ?? null,
            'status' => $clerkIdentity ? 'pending_admin_approval' : 'pending_email_verification',
            'email_verified_at' => $clerkIdentity ? now() : null,
            'created_by' => 'self',
        ]);

        foreach ($request->file('documents', []) as $file) {
            $seller->documents()->create([
                'label' => $file->getClientOriginalName(),
                'file_path' => $file->store('seller-documents', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        if ($clerkIdentity) {
            session()->forget('seller_clerk_identity');
        } else {
            try {
                Mail::to($seller->email)->send(new SellerActivationMail($seller));
            } catch (\Throwable $exception) {
                Log::error('Failed to queue seller activation email.', [
                    'seller_id' => $seller->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->route('seller.registration.submitted');
    }
}
