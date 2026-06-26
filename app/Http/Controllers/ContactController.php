<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        $title = SiteSetting::getValue('contact_page_title_ar', __('ui.pages.contact'));
        $intro = SiteSetting::getValue('contact_page_intro_ar', __('ui.contact.intro_default'));

        return view('pages.contact', [
            'title' => $title,
            'intro' => $intro,
            'formLabels' => [
                'name' => SiteSetting::getValue('contact_form_name_label_ar', __('ui.contact.name')),
                'email' => SiteSetting::getValue('contact_form_email_label_ar', 'رقم الهاتف'),
                'subject' => SiteSetting::getValue('contact_form_subject_label_ar', __('ui.contact.subject')),
                'message' => SiteSetting::getValue('contact_form_message_label_ar', __('ui.contact.message')),
                'submit' => SiteSetting::getValue('contact_form_submit_label_ar', __('ui.contact.send')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        if (! empty($validated['website'])) {
            return redirect()
                ->route('contact')
                ->with('success', SiteSetting::getValue('contact_page_success_ar', __('ui.contact.sent')));
        }

        ContactMessage::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        return redirect()
            ->route('contact')
            ->with('success', SiteSetting::getValue('contact_page_success_ar', __('ui.contact.sent')));
    }
}
