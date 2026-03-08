import { type User } from './auth';

export type Client = {
    id: number;
    tenant_id: string;
    name: string;
    email: string | null;
    phone: string | null;
    company: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
    matters_count?: number;
};

export type Matter = {
    id: number;
    tenant_id: string;
    client_id: number;
    title: string;
    description: string | null;
    reference_number: string | null;
    status: MatterStatus;
    created_at: string;
    updated_at: string;
    documents_count?: number;
    client?: Client;
};

export type MatterStatus = 'open' | 'closed' | 'on_hold';

export type DocumentStatus =
    | 'uploaded'
    | 'scanning'
    | 'scan_passed'
    | 'scan_failed'
    | 'extracting'
    | 'extraction_failed'
    | 'classifying'
    | 'classification_failed'
    | 'ready_for_review'
    | 'reviewed'
    | 'approved';

export type DocumentClassification = {
    provider: string;
    type: string;
    confidence: number | string | null;
    metadata?: Record<string, unknown> | null;
};

export type DocumentChannelSnapshot = {
    title: string;
    matter_title: string | null;
};

export type Document = {
    id: number;
    tenant_id: string;
    matter_id: number;
    uploaded_by: number | null;
    title: string;
    file_path: string;
    file_name: string;
    mime_type: string | null;
    file_size: number;
    status: DocumentStatus;
    created_at: string;
    updated_at: string;
    matter?: Matter;
    uploader?: User | null;
    classification?: DocumentClassification | null;
};

export type DocumentActivity = {
    id: number;
    action: string;
    created_at: string;
    user: Pick<User, 'id' | 'name'> | null;
    ip_address: string | null;
};

export type PaginatedData<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

export type QueueHealthQueue = {
    name: string;
    pipeline: string;
    messages: number;
    messages_ready: number;
    messages_unacknowledged: number;
    consumers: number;
    state: string;
    is_dead_letter: boolean;
};

export type QueueHealthSummary = {
    total_messages: number;
    total_ready: number;
    total_unacked: number;
    total_consumers: number;
    dead_letter_messages: number;
};

export type QueueHealthSnapshot = {
    available: boolean;
    generated_at: string;
    queues: QueueHealthQueue[];
    summary: QueueHealthSummary;
    error: string | null;
};

export type DashboardPipelineDocument = {
    id: number;
    title: string;
    status: DocumentStatus;
    matter_title: string | null;
    updated_at: string;
};

export type DashboardStats = {
    processed_today: number;
    pending_review: number;
    failed: number;
};

export type DashboardFailureDocument = {
    id: number;
    title: string;
    status: DocumentStatus;
    matter_title: string | null;
    updated_at: string;
};
