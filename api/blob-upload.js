import crypto from 'node:crypto';
import { handleUpload } from '@vercel/blob/client';

const MAX_BYTES = 250 * 1024 * 1024;
const ALLOWED_CONTENT_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'video/mp4',
    'video/webm',
    'video/quicktime',
    'video/x-m4v',
];

function verifyAuthorization(value) {
    const [payload, signature] = String(value || '').split('.');
    const appKey = process.env.APP_KEY || '';

    if (!payload || !signature || !appKey) {
        throw new Error('Upload authorization is invalid.');
    }

    const expected = crypto.createHmac('sha256', appKey).update(payload).digest('hex');
    const expectedBuffer = Buffer.from(expected, 'utf8');
    const signatureBuffer = Buffer.from(signature, 'utf8');

    if (expectedBuffer.length !== signatureBuffer.length || !crypto.timingSafeEqual(expectedBuffer, signatureBuffer)) {
        throw new Error('Upload authorization is invalid.');
    }

    const padded = payload.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(payload.length / 4) * 4, '=');
    const claims = JSON.parse(Buffer.from(padded, 'base64').toString('utf8'));

    if (!claims.user_id || Number(claims.expires) < Math.floor(Date.now() / 1000)) {
        throw new Error('Upload authorization has expired.');
    }

    if (!ALLOWED_CONTENT_TYPES.includes(claims.type) || Number(claims.max_bytes) < 1) {
        throw new Error('This media type is not allowed.');
    }

    return claims;
}

export default async function handler(request, response) {
    if (request.method !== 'POST') {
        response.setHeader('Allow', 'POST');
        return response.status(405).json({ error: 'Method not allowed.' });
    }

    try {
        const body = typeof request.body === 'string' ? JSON.parse(request.body) : request.body;
        const result = await handleUpload({
            body,
            request,
            token: process.env.BLOB_READ_WRITE_TOKEN,
            onBeforeGenerateToken: async (pathname, clientPayload) => {
                if (!String(pathname).startsWith('news/manual/')) {
                    throw new Error('Invalid upload path.');
                }

                const payload = JSON.parse(clientPayload || '{}');
                const claims = verifyAuthorization(payload.authorization);

                return {
                    allowedContentTypes: [claims.type],
                    maximumSizeInBytes: Math.min(Number(claims.max_bytes), MAX_BYTES),
                    addRandomSuffix: true,
                    tokenPayload: JSON.stringify({ userId: claims.user_id }),
                };
            },
            onUploadCompleted: async () => {},
        });

        return response.status(200).json(result);
    } catch (error) {
        return response.status(400).json({
            error: error instanceof Error ? error.message : 'Upload failed.',
        });
    }
}
