@php
    $mediaStatePath = str_replace('blob_upload', 'image_url', $getStatePath());
    $currentUrl = (string) ($field->getRecord()?->image_url ?? '');
    $currentIsVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $currentUrl) === 1;
    $mediaUploaderAsset = \Illuminate\Support\Facades\Vite::asset('resources/js/admin-media-upload.js');
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            uploading: false,
            dragging: false,
            progress: 0,
            error: '',
            url: @js($currentUrl),
            isVideo: @js($currentIsVideo),
            async choose(event) {
                await this.upload(event.target.files?.[0]);
                event.target.value = '';
            },
            async drop(event) {
                this.dragging = false;
                await this.upload(event.dataTransfer?.files?.[0]);
            },
            async upload(file) {
                if (! file) return;

                const allowed = [
                    'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                    'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v',
                ];

                if (! allowed.includes(file.type)) {
                    this.error = 'Choose a JPG, PNG, WebP, GIF, MP4, WebM, MOV, or M4V file.';
                    return;
                }

                if (file.size > 250 * 1024 * 1024) {
                    this.error = 'The maximum media size is 250 MB.';
                    return;
                }

                this.uploading = true;
                this.progress = 0;
                this.error = '';

                try {
                    const response = await fetch(@js(route('admin.blob-upload.authorize')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                        },
                        body: JSON.stringify({
                            name: file.name,
                            type: file.type,
                            size: file.size,
                        }),
                    });
                    const payload = await response.json();

                    if (! response.ok || ! payload.authorization) {
                        throw new Error(payload.message || 'Could not authorize upload.');
                    }

                    const { uploadArticleMedia } = await import(@js($mediaUploaderAsset));
                    const blob = await uploadArticleMedia(file, payload.authorization, (percentage) => {
                        this.progress = Math.round(percentage);
                    });

                    this.url = blob.url;
                    this.isVideo = file.type.startsWith('video/');
                    this.progress = 100;
                    $wire.set(@js($mediaStatePath), blob.url);
                } catch (error) {
                    this.error = error.message || 'Upload failed.';
                } finally {
                    this.uploading = false;
                }
            },
            remove() {
                this.url = '';
                this.isVideo = false;
                this.progress = 0;
                $wire.set(@js($mediaStatePath), null);
            },
        }"
        class="space-y-4"
    >
        <label
            x-on:dragenter.prevent="dragging = true"
            x-on:dragover.prevent="dragging = true"
            x-on:dragleave.prevent="dragging = false"
            x-on:drop.prevent="drop($event)"
            x-bind:class="dragging ? 'border-primary-500 bg-primary-50' : 'border-gray-300 bg-white hover:border-primary-400'"
            class="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 text-center transition dark:border-gray-600 dark:bg-gray-900"
        >
            <input
                type="file"
                class="sr-only"
                accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/x-m4v"
                x-on:change="choose($event)"
                x-bind:disabled="uploading"
            >

            <svg class="mb-3 h-10 w-10 text-primary-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
            </svg>

            <span x-show="! uploading" class="text-sm font-bold text-gray-800 dark:text-gray-100">
                Drag and drop an image or video here
            </span>
            <span x-show="! uploading" class="mt-1 text-xs text-gray-500">
                or click to browse - images and videos up to 250 MB
            </span>
            <span x-show="uploading" class="text-sm font-bold text-primary-700">
                Uploading to Shaghilla media... <span x-text="progress + '%'"></span>
            </span>
        </label>

        <div x-show="uploading" class="h-2 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full bg-primary-600 transition-all" x-bind:style="`width: ${progress}%`"></div>
        </div>

        <p x-show="error" x-text="error" class="text-sm text-danger-600"></p>

        <div x-show="url" class="relative overflow-hidden rounded-2xl border border-gray-200 bg-gray-950/5 p-2">
            <img x-show="url && ! isVideo" x-bind:src="url" alt="Article image preview" class="max-h-72 w-full rounded-xl object-contain">
            <video x-show="url && isVideo" x-bind:src="url" controls preload="metadata" class="max-h-80 w-full rounded-xl bg-black"></video>
            <button
                type="button"
                x-on:click="remove()"
                class="absolute right-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-xs font-bold text-danger-600 shadow"
            >
                Remove
            </button>
        </div>
    </div>
</x-dynamic-component>
