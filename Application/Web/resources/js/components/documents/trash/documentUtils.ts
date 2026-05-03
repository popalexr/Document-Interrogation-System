import type { DeletedDocument } from './types';

export function formatSize(bytes: number | undefined): string {
    if (!bytes && bytes !== 0) return '';

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

export function formatDate(value: string | Date | undefined): string {
    if (!value) return '';

    const date = typeof value === 'string' ? new Date(value) : value;

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export function fileExt(document: DeletedDocument | null | undefined): string {
    const name = document?.original_name;
    if (!name) return '';

    const index = name.lastIndexOf('.');
    if (index === -1) return '';

    return name.slice(index + 1).toUpperCase();
}

export function extClasses(extension: string): string {
    switch (extension) {
        case 'PDF':
            return 'border-red-200 bg-red-50 text-red-600';
        case 'DOC':
        case 'DOCX':
            return 'border-blue-200 bg-blue-50 text-blue-600';
        case 'XLS':
        case 'XLSX':
            return 'border-green-200 bg-green-50 text-green-600';
        case 'PPT':
        case 'PPTX':
            return 'border-orange-200 bg-orange-50 text-orange-600';
        default:
            return 'border-slate-200 bg-white text-slate-700';
    }
}

export function documentIcon(document: DeletedDocument): string {
    switch (fileExt(document)) {
        case 'DOC':
        case 'DOCX':
            return 'vscode-icons:file-type-word';
        case 'PDF':
            return 'vscode-icons:file-type-pdf2';
        case 'PPT':
        case 'PPTX':
            return 'vscode-icons:file-type-powerpoint';
        case 'XLS':
        case 'XLSX':
        case 'CSV':
            return 'vscode-icons:file-type-excel';
        case 'MD':
        case 'TXT':
        case 'TEX':
            return 'vscode-icons:file-type-text';
        case 'JSON':
            return 'vscode-icons:file-type-json';
        default:
            return 'vscode-icons:default-file';
    }
}
