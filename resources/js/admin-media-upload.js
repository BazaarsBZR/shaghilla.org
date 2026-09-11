import { upload } from '@vercel/blob/client';

function safeFilename(name) {
    return name
        .normalize('NFKD')
        .replace(/[^a-zA-Z0-9._-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(-120) || 'media';
}

async function uploadArticleMedia(file, authorization, onProgress = () => {}) {
    const pathname = `news/manual/${Date.now()}-${crypto.randomUUID()}-${safeFilename(file.name)}`;

    return upload(pathname, file, {
        access: 'public',
        handleUploadUrl: '/api/blob-upload',
        clientPayload: JSON.stringify({ authorization }),
        contentType: file.type,
        multipart: file.size > 4 * 1024 * 1024,
        onUploadProgress: ({ percentage }) => onProgress(percentage),
    });
}

window.uploadArticleMedia = uploadArticleMedia;
