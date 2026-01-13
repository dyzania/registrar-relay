export type TransactionType = 
  | 'grade_request'
  | 'enrollment'
  | 'document_request'
  | 'payment'
  | 'clearance'
  | 'other';

export type QueueStatus = 'waiting' | 'in_progress' | 'completed' | 'cancelled';

export interface QueueItem {
  id: string;
  queue_number: number;
  transaction_type: TransactionType;
  student_name: string;
  student_id: string | null;
  status: QueueStatus;
  window_id: number | null;
  created_at: string;
  called_at: string | null;
  completed_at: string | null;
}

export interface Window {
  id: number;
  window_number: number;
  is_active: boolean;
  current_queue_id: string | null;
  disabled_services: string[] | null;
  created_at: string;
}

export interface Feedback {
  id: string;
  queue_id: string;
  rating: number;
  comment: string | null;
  created_at: string;
}

export const TRANSACTION_LABELS: Record<TransactionType, string> = {
  grade_request: 'Grade Request',
  enrollment: 'Enrollment',
  document_request: 'Document Request',
  payment: 'Payment',
  clearance: 'Clearance',
  other: 'Other',
};
