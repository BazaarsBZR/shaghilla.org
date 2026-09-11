<footer class="relative overflow-hidden border-t border-white/10 text-white" style="background-color: #082a33; color: #ffffff;">
    <div class="h-1 bg-gradient-to-l from-accent via-white to-[#16865c]"></div>
    <div class="pointer-events-none absolute -left-20 top-10 h-48 w-48 rounded-full bg-[#16865c]/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-16 bottom-0 h-44 w-44 rounded-full bg-accent/10 blur-3xl"></div>

    @php
        $brandName = \App\Models\SiteSetting::getValue('site_brand_name', config('app.name'));
    @endphp

    <div class="relative mx-auto w-full max-w-7xl px-5 py-10 sm:px-8 lg:py-12">
        <div class="grid gap-9 border-b border-white/10 pb-9 sm:grid-cols-2 lg:grid-cols-[1.35fr_1fr_1fr_1.15fr] lg:gap-10">
            <section class="space-y-4 sm:col-span-2 lg:col-span-1">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-xl font-black tracking-tight text-white">
                    <img src="{{ asset('website-logo.png') }}" alt="{{ $brandName }}" class="h-14 w-14 rounded-full bg-white object-contain p-1.5 shadow-lg ring-1 ring-white/20" />
                    <span>{{ $brandName }}</span>
                </a>
                <p class="max-w-sm text-sm font-medium leading-7 text-white/65">
                    صوت الناس وأخبار لبنان، مع بيانات المال العام والمناقصات الحكومية في منصة عربية واضحة ومتجددة.
                </p>
                <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-bold text-white/70">منصة لبنانية مستقلة</span>
            </section>

            <section>
                <h2 class="mb-4 text-sm font-black text-white">الأقسام الرئيسية</h2>
                <nav class="grid gap-3 text-sm font-bold" aria-label="روابط الموقع الرئيسية">
                    <a href="{{ route('home') }}" class="text-white/65 transition hover:text-white">الرئيسية</a>
                    <a href="{{ route('home') }}#latest-news" class="text-white/65 transition hover:text-white">آخر الأخبار</a>
                    <a href="{{ route('public-money.index') }}" class="text-white/65 transition hover:text-white">{{ __('ui.nav.public_money') }}</a>
                    <a href="{{ route('financial-status.index') }}" class="text-white/65 transition hover:text-white">الوضع المالي</a>
                    <a href="{{ route('government-tenders.index') }}" class="text-white/65 transition hover:text-white">المناقصات الحكومية</a>
                </nav>
            </section>

            <section>
                <h2 class="mb-4 text-sm font-black text-white">الخدمات والمشاركة</h2>
                <nav class="grid gap-3 text-sm font-bold" aria-label="روابط الخدمات">
                    <a href="{{ route('membership') }}" class="text-white/65 transition hover:text-white">الانتساب إلى الرابطة</a>
                    <a href="{{ route('contact') }}" class="text-white/65 transition hover:text-white">طلب خدمة</a>
                    <a href="{{ route('government-tenders.index') }}" class="text-white/65 transition hover:text-white">فرص المناقصات</a>
                    <a href="{{ route('public-money.index') }}" class="text-white/65 transition hover:text-white">متابعة المال العام</a>
                </nav>
            </section>

            <section>
                <h2 class="mb-4 text-sm font-black text-white">تواصل معنا</h2>
                <div class="grid gap-3 text-sm font-bold">
                    <a href="mailto:rabitat@shaghilla.org" dir="ltr" class="w-fit text-white/70 transition hover:text-white">rabitat@shaghilla.org</a>
                    <a href="tel:+96179333415" dir="ltr" class="w-fit text-white/70 transition hover:text-white">+961 79 333 415</a>
                    <a href="https://wa.me/96179333415" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex w-fit items-center justify-center rounded-full bg-[#16865c] px-5 py-2.5 text-sm font-black text-white shadow-lg transition hover:bg-[#0f704c]">
                        تواصل عبر واتساب
                    </a>
                </div>
                <p class="mt-5 max-w-xs text-xs font-semibold leading-6 text-white/45">
                    للاستفسارات، طلب الخدمات، والمساعدة في فهم فرص المناقصات.
                </p>
            </section>
        </div>

        <div class="flex flex-col gap-2 pt-6 text-xs font-semibold text-white/45 sm:flex-row sm:items-center sm:justify-between">
            <div>© {{ now()->year }} {{ $brandName }}. جميع الحقوق محفوظة.</div>
            <div>لبنان</div>
        </div>
    </div>
</footer>
