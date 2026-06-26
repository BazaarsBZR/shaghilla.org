<x-layouts.site :title="$pageTitle ?? __('ui.pages.membership')">
    <div class="mx-auto max-w-3xl space-y-8">
        <header class="space-y-2 text-right">
            <h1 class="text-2xl font-extrabold tracking-tight text-ink sm:text-3xl">
                {{ $pageTitle ?? __('ui.pages.membership') }}
            </h1>
            @if (! empty($intro))
                <p class="text-sm leading-relaxed text-ink-muted">
                    {{ $intro }}
                </p>
            @endif
        </header>

        @if (session('success'))
            <div class="sh-alert-success text-right">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->has('session'))
            <div class="sh-alert-error text-right">
                {{ $errors->first('session') }}
            </div>
        @endif

        @if (! empty($letterHtml))
            <section class="rounded-2xl bg-surface p-6 text-right shadow-surface ring-1 ring-line">
                <div class="max-w-none space-y-4 text-sm leading-relaxed text-ink [&_a]:text-ink [&_a]:underline [&_blockquote]:border-r-4 [&_blockquote]:border-line [&_blockquote]:pr-4 [&_blockquote]:text-ink-muted [&_h2]:text-lg [&_h2]:font-extrabold [&_h3]:text-base [&_h3]:font-extrabold [&_li]:mb-2 [&_ol]:list-decimal [&_ol]:pr-6 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:pr-6">
                    {!! str($letterHtml)->sanitizeHtml() !!}
                </div>
            </section>
        @endif

        <form
            method="POST"
            action="{{ route('membership.store') }}"
            enctype="multipart/form-data"
            class="sh-form-shell"
            x-data="{
                previewUrl: null,
                showPreview: false,
                fileName: @js(old('id_document') ? 'تم اختيار ملف سابقاً' : null),
                fileIsImage: false,
                volunteerAreas: @js(old('volunteer_areas', [])),
                hasExperience: @js(old('has_volunteer_experience')),
                clearPreview() {
                    if (this.previewUrl) {
                        URL.revokeObjectURL(this.previewUrl);
                    }
                    this.previewUrl = null;
                    this.showPreview = false;
                },
                handleIdDocumentChange(event) {
                    this.clearPreview();

                    const file = event.target.files?.[0];
                    if (!file) {
                        this.fileName = null;
                        this.fileIsImage = false;
                        return;
                    }

                    this.fileName = file.name;
                    this.fileIsImage = (file.type || '').startsWith('image/');

                    if (this.fileIsImage) {
                        this.previewUrl = URL.createObjectURL(file);
                        this.showPreview = true;
                    }
                },
            }"
        >
            @csrf

            <h2 class="text-right text-base font-extrabold text-ink">البيانات الشخصية</h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="sh-form-label" for="full_name">الاسم الثلاثي</label>
                    <input
                        id="full_name"
                        name="full_name"
                        type="text"
                        value="{{ old('full_name') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('full_name')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="mother_name">اسم الأم</label>
                    <input
                        id="mother_name"
                        name="mother_name"
                        type="text"
                        value="{{ old('mother_name') }}"
                        class="sh-form-field"
                    />
                    @error('mother_name')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="birth_date">تاريخ الولادة</label>
                    <input
                        id="birth_date"
                        name="birth_date"
                        type="date"
                        value="{{ old('birth_date') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('birth_date')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="registry_number">رقم السجل</label>
                    <input
                        id="registry_number"
                        name="registry_number"
                        type="text"
                        inputmode="numeric"
                        value="{{ old('registry_number') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('registry_number')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="registration_place">مكان القيد</label>
                    <input
                        id="registration_place"
                        name="registration_place"
                        type="text"
                        value="{{ old('registration_place') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('registration_place')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="phone">رقم الهاتف</label>
                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        inputmode="tel"
                        value="{{ old('phone') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('phone')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="emergency_phone">رقم هاتف للطوارئ</label>
                    <input
                        id="emergency_phone"
                        name="emergency_phone"
                        type="tel"
                        inputmode="tel"
                        value="{{ old('emergency_phone') }}"
                        class="sh-form-field"
                    />
                    @error('emergency_phone')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="sh-form-label" for="email">البريد الإلكتروني</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        class="sh-form-field"
                    />
                    @error('email')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="sh-form-label" for="address">العنوان</label>
                    <input
                        id="address"
                        name="address"
                        type="text"
                        value="{{ old('address') }}"
                        class="sh-form-field"
                    />
                    @error('address')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="profession">المهنة</label>
                    <input
                        id="profession"
                        name="profession"
                        type="text"
                        value="{{ old('profession') }}"
                        class="sh-form-field"
                    />
                    @error('profession')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label">الحالة الاجتماعية</label>
                    <div class="mt-2 flex items-center gap-4 text-sm font-semibold text-ink-muted" dir="rtl">
                        <label class="inline-flex items-center gap-2">
                            <input
                                type="radio"
                                name="marital_status"
                                value="married"
                                @checked(old('marital_status') === 'married')
                                class="text-accent focus:ring-accent/25"
                            />
                            <span>متزوّج</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input
                                type="radio"
                                name="marital_status"
                                value="single"
                                @checked(old('marital_status') === 'single')
                                class="text-accent focus:ring-accent/25"
                            />
                            <span>غير متزوّج</span>
                        </label>
                    </div>
                    @error('marital_status')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="children_count">عدد الأولاد</label>
                    <input
                        id="children_count"
                        name="children_count"
                        type="number"
                        min="0"
                        max="30"
                        value="{{ old('children_count') }}"
                        class="sh-form-field"
                    />
                    @error('children_count')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="blood_type">فئة الدم</label>
                    <select
                        id="blood_type"
                        name="blood_type"
                        class="sh-form-field"
                    >
                        <option value="">—</option>
                        @foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $type)
                            <option value="{{ $type }}" @selected(old('blood_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('blood_type')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="space-y-2 rounded-2xl border border-line bg-surface-soft p-4 text-right">
                <h2 class="text-sm font-extrabold text-ink">بيانات التطوّع</h2>
                <p class="text-sm leading-relaxed text-ink-muted">
                    يرغب مقدّم الطلب بالتطوّع ضمن المجالات التالية (يمكن اختيار أكثر من خيار):
                </p>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @php
                        $volunteerOptions = [
                            'organizational' => 'تنظيمي',
                            'social' => 'اجتماعي',
                            'relief' => 'إغاثي',
                            'health' => 'صحي',
                            'media' => 'إعلامي',
                            'education' => 'تربوي / تعليمي',
                            'logistics' => 'لوجستي',
                            'administrative' => 'إداري',
                            'field' => 'ميداني',
                            'other' => 'أخرى',
                        ];
                    @endphp

                    @foreach ($volunteerOptions as $value => $label)
                        <label class="flex items-center gap-3 rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-ink shadow-surface">
                            <input
                                type="checkbox"
                                name="volunteer_areas[]"
                                value="{{ $value }}"
                                class="text-accent focus:ring-accent/25"
                                x-model="volunteerAreas"
                            />
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                @error('volunteer_areas')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror
                @error('volunteer_areas.*')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror

                <div x-cloak x-show="volunteerAreas.includes('other')" class="space-y-2">
                    <label class="sh-form-label" for="volunteer_other">أخرى: (يرجى التوضيح)</label>
                    <input
                        id="volunteer_other"
                        name="volunteer_other"
                        type="text"
                        value="{{ old('volunteer_other') }}"
                        class="sh-form-field"
                    />
                    @error('volunteer_other')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="space-y-2">
                    <div class="text-sm font-semibold text-ink-muted">هل لديك خبرة سابقة في العمل التطوّعي؟</div>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink">
                            <input
                                type="radio"
                                name="has_volunteer_experience"
                                value="1"
                                class="text-accent focus:ring-accent/25"
                                x-model="hasExperience"
                                @checked(old('has_volunteer_experience') === '1')
                            />
                            <span>نعم</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink">
                            <input
                                type="radio"
                                name="has_volunteer_experience"
                                value="0"
                                class="text-accent focus:ring-accent/25"
                                x-model="hasExperience"
                                @checked(old('has_volunteer_experience') === '0')
                            />
                            <span>لا</span>
                        </label>
                    </div>
                    @error('has_volunteer_experience')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror

                    <div x-cloak x-show="hasExperience === '1'" class="space-y-2">
                        <label class="sh-form-label" for="volunteer_experience_details">
                            في حال نعم، يرجى التوضيح باختصار:
                        </label>
                        <textarea
                            id="volunteer_experience_details"
                            name="volunteer_experience_details"
                            rows="4"
                            class="sh-form-field"
                        >{{ old('volunteer_experience_details') }}</textarea>
                        @error('volunteer_experience_details')
                            <div class="sh-form-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <label class="sh-form-label" for="id_document">{{ $idLabel ?? 'صورة الهوية / جواز السفر' }}</label>
                <div class="text-sm font-semibold text-ink-muted">
                    يمكنك <span class="font-extrabold text-ink">إرفاق ملف</span> (PDF) أو <span class="font-extrabold text-ink">رفع صورة</span>.
                </div>

                <input
                    id="id_document"
                    name="id_document"
                    type="file"
                    accept="image/*,application/pdf"
                    class="sr-only"
                    x-ref="idDocInput"
                    @change="handleIdDocumentChange($event)"
                />

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button
                        type="button"
                        class="sh-btn-secondary"
                        @click="
                            $refs.idDocInput.value = null;
                            $refs.idDocInput.removeAttribute('capture');
                            $refs.idDocInput.setAttribute('accept', 'image/*,application/pdf');
                            $refs.idDocInput.click();
                        "
                    >
                        إرفاق ملف
                    </button>

                    <button
                        type="button"
                        class="sh-btn-primary"
                        @click="
                            $refs.idDocInput.value = null;
                            $refs.idDocInput.setAttribute('accept', 'image/*');
                            $refs.idDocInput.setAttribute('capture', 'environment');
                            $refs.idDocInput.click();
                        "
                    >
                        رفع صورة
                    </button>
                </div>

                <div class="rounded-xl border border-line bg-surface px-4 py-3 text-sm font-semibold text-ink ring-1 ring-line/60">
                    <span x-text="fileName ? fileName : 'لم يتم اختيار ملف بعد.'"></span>
                </div>

                @error('id_document')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror

                <template x-if="fileIsImage && showPreview && previewUrl">
                    <div class="overflow-hidden rounded-2xl ring-1 ring-line">
                        <img :src="previewUrl" alt="" class="h-auto w-full object-cover" />
                    </div>
                </template>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="sh-form-label" for="signature_name">التوقيع (الاسم)</label>
                    <input
                        id="signature_name"
                        name="signature_name"
                        type="text"
                        value="{{ old('signature_name') }}"
                        class="sh-form-field"
                    />
                    @error('signature_name')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="text-right text-sm font-semibold text-ink-muted">
                    <div class="sh-form-label">التاريخ</div>
                    <div class="mt-2 rounded-xl border border-line bg-surface-soft px-4 py-3 text-ink">
                        {{ now()->format('Y-m-d') }}
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-line bg-surface-soft p-4 text-right text-sm leading-relaxed text-ink-muted">
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        name="agree"
                        value="1"
                        class="mt-1 text-accent focus:ring-accent/25"
                        @checked(old('agree'))
                        required
                    />
                    <span>
                        أتعهد بالالتزام بالنظام الداخلي لرابطة الشغيلــة، واحترام قراراتها وتوجيهاتها التنظيميّة، والاستعداد لتحمّل المسؤوليات التطوّعيّة والتنظيميّة بما يخدم أهداف الرابطة ومصلحة العمل الجماعي.
                    </span>
                </label>
                @error('agree')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="hidden">
                <input id="website" name="website" type="text" value="{{ old('website') }}" autocomplete="off" tabindex="-1" />
            </div>

            <button
                type="submit"
                class="sh-btn-primary w-full"
            >
                {{ $submitLabel ?? 'إرسال الطلب' }}
            </button>
        </form>
    </div>
</x-layouts.site>
