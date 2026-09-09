@php
    $imageStatePath = str_replace('blob_upload', 'image_url', $getStatePath());
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            uploading: false,
            error: '',
            url: @js($get('image_url')),
            async upload(event) {
                const file = event.target.files?.[0];
                if (! file) return;

                this.uploading = true;
                this.error = '';

                const form = new FormData();
                form.append('image', file);

                try {
                    const response = await fetch(@js(route('admin.blob-upload')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                        },
                        body: form,
                    });
                    const payload = await response.json();

                    if (! response.ok || ! payload.url) {
                        throw new Error(payload.message || 'Upload failed.');
                    }

                    this.url = payload.url;
                    $wire.set(@js($imageStatePath), payload.url);
                } catch (error) {
                    this.error = error.message || 'Upload failed.';
                } finally {
                    this.uploading = false;
                    event.target.value = '';
                }
            },
        }"
        class="space-y-3"
    >
        <label class="flex cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-6 py-8 text-center transition hover:border-primary-500 dark:border-gray-600 dark:bg-gray-900">
            <input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp,image/gif" x-on:change="upload($event)" x-bind:disabled="uploading">
            <span x-show="! uploading" class="text-sm font-medium text-gray-700 dark:text-gray-200">Choose an image (JPG, PNG, WebP or GIF, max 4 MB)</span>
            <span x-show="uploading" class="text-sm font-medium text-primary-600">Uploading to Shaghilla media...</span>
        </label>

        <p x-show="error" x-text="error" class="text-sm text-danger-600"></p>
        <img x-show="url" x-bind:src="url" alt="Featured image preview" class="max-h-64 w-full rounded-xl object-cover">
    </div>
</x-dynamic-component>
