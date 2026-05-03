import { Check, CircleAlert, CircleDashed } from 'lucide-vue-next';
import type { Component } from 'vue';
import type { StatusFilter, UploadItem } from './types';

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

export function fileExt(upload: UploadItem | null | undefined): string {
    const name = upload?.original_name;
    if (!name) return '';

    const index = name.lastIndexOf('.');
    if (index === -1) return '';

    return name.slice(index + 1).toUpperCase();
}

export function statusKind(upload: UploadItem): Exclude<StatusFilter, 'all'> {
    if (upload.status === 'uploaded') return 'indexed';
    if (upload.status === 'uploading') return 'processing';
    if (['failed', 'error'].includes(upload.status)) return 'failed';

    return 'not_indexed';
}

export function canAskAi(upload: UploadItem): boolean {
    return statusKind(upload) === 'indexed';
}

export function statusLabel(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'Indexed';
        case 'processing':
            return 'Processing';
        case 'failed':
            return 'Failed';
        default:
            return 'Not indexed';
    }
}

export function statusDescription(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'Searchable';
        case 'processing':
            return 'Indexing';
        case 'failed':
            return 'Extraction error';
        default:
            return 'Queued';
    }
}

export function statusClasses(upload: UploadItem): string {
    switch (statusKind(upload)) {
        case 'indexed':
            return 'border-green-200 bg-green-50 text-green-700';
        case 'processing':
            return 'border-orange-200 bg-orange-50 text-orange-700';
        case 'failed':
            return 'border-red-200 bg-red-50 text-red-700';
        default:
            return 'border-slate-200 bg-slate-100 text-slate-700';
    }
}

export function statusIcon(upload: UploadItem): Component {
    switch (statusKind(upload)) {
        case 'indexed':
            return Check;
        case 'processing':
            return CircleDashed;
        case 'failed':
            return CircleAlert;
        default:
            return CircleDashed;
    }
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

export function documentIcon(upload: UploadItem): string {
    switch (fileExt(upload)) {
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
