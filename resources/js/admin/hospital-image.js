export function imageValidationError(file) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return 'Choose a JPG, PNG or WebP image.';
    if (file.size === 0 || file.size > 5 * 1024 * 1024) return 'Choose an image smaller than 5 MB.';
    return null;
}

export async function prepareHospitalImage(file) {
    const error = imageValidationError(file);
    if (error) throw new Error(error);
    const url = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = url;
        await image.decode();
        const ratio = Math.min(1, 1000 / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.naturalWidth * ratio));
        canvas.height = Math.max(1, Math.round(image.naturalHeight * ratio));
        const context = canvas.getContext('2d');
        context.fillStyle = '#faf8f5';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.8);
    } catch {
        throw new Error('This image could not be opened. Choose another image.');
    } finally {
        URL.revokeObjectURL(url);
    }
}
