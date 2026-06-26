<?php

namespace App\Http\Controllers;

use App\Models\MembershipApplication;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MembershipController extends Controller
{
    public function show(Request $request): Response
    {
        $request->session()->regenerateToken();

        $pageTitle = SiteSetting::getValue('membership_page_title_ar', 'طلب انتساب إلى رابطة الشغيلــة');
        $intro = SiteSetting::getValue('membership_page_intro_ar');
        if (blank($intro)) {
            $intro = __('ui.membership.intro_default');
        }
        $letterHtml = SiteSetting::getValue('membership_page_letter_html_ar');

        return response()
            ->view('pages.membership', [
                'pageTitle' => $pageTitle,
                'intro' => $intro,
                'letterHtml' => $letterHtml,
                'successMessage' => SiteSetting::getValue('membership_form_success_ar', 'تم إرسال طلب الانتساب بنجاح.'),
                'submitLabel' => SiteSetting::getValue('membership_form_submit_label_ar', 'إرسال الطلب'),
                'idLabel' => SiteSetting::getValue('membership_form_id_label_ar', 'صورة الهوية / جواز السفر'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['required', 'date'],
            'registry_number' => ['required', 'string', 'max:50'],
            'registration_place' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'emergency_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'profession' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'in:married,single'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:30'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'volunteer_areas' => ['nullable', 'array', 'max:15'],
            'volunteer_areas.*' => ['string', 'in:organizational,social,relief,health,media,education,logistics,administrative,field,other'],
            'volunteer_other' => ['nullable', 'string', 'max:255'],
            'has_volunteer_experience' => ['nullable', 'boolean'],
            'volunteer_experience_details' => ['nullable', 'string', 'max:2000'],
            'signature_name' => ['nullable', 'string', 'max:255'],
            'agree' => ['accepted'],
            'id_document' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,heic,heif'], // 10MB
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        if (! empty($validated['website'])) {
            return redirect()
                ->route('membership')
                ->with('success', SiteSetting::getValue('membership_form_success_ar', 'تم إرسال طلب الانتساب بنجاح.'));
        }

        $volunteerAreas = array_values($validated['volunteer_areas'] ?? []);
        if (in_array('other', $volunteerAreas, true) && blank($validated['volunteer_other'] ?? null)) {
            return back()
                ->withErrors(['volunteer_other' => 'يرجى كتابة المجال الآخر.'])
                ->withInput();
        }

        $hasVolunteerExperience = null;
        if ($request->has('has_volunteer_experience')) {
            $hasVolunteerExperience = $request->boolean('has_volunteer_experience');
        }
        if ($hasVolunteerExperience === true && blank($validated['volunteer_experience_details'] ?? null)) {
            return back()
                ->withErrors(['volunteer_experience_details' => 'يرجى توضيح الخبرة التطوعية باختصار.'])
                ->withInput();
        }

        $idDocumentPath = null;
        if ($request->hasFile('id_document')) {
            $idDocumentPath = $request->file('id_document')->store('membership/id-documents', 'local');
        }

        MembershipApplication::create([
            'full_name' => $validated['full_name'],
            'mother_name' => $validated['mother_name'] ?? null,
            'birth_date' => $validated['birth_date'],
            'registry_number' => $validated['registry_number'] ?? null,
            'registration_place' => $validated['registration_place'] ?? null,
            'phone' => $validated['phone'],
            'emergency_phone' => $validated['emergency_phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'profession' => $validated['profession'] ?? null,
            'marital_status' => $validated['marital_status'] ?? null,
            'children_count' => $validated['children_count'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null,
            'volunteer_areas' => $volunteerAreas !== [] ? $volunteerAreas : null,
            'volunteer_other' => $validated['volunteer_other'] ?? null,
            'has_volunteer_experience' => $hasVolunteerExperience,
            'volunteer_experience_details' => $validated['volunteer_experience_details'] ?? null,
            'id_document_path' => $idDocumentPath,
            'signature_name' => $validated['signature_name'] ?? null,
            'status' => MembershipApplication::STATUS_PENDING,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()
            ->route('membership')
            ->with('success', SiteSetting::getValue('membership_form_success_ar', 'تم إرسال طلب الانتساب بنجاح.'));
    }
}
