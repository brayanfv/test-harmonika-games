export type PeriodClosingStatus = 'pending' | 'processing' | 'sent' | 'failed';

export type PeriodClosing = {
  id: number;
  user_id: number;
  start_date: string;
  end_date: string;
  status: PeriodClosingStatus;
  attempts: number;
  dispatched_at: string | null;
  processing_at: string | null;
  sent_at: string | null;
  failed_at: string | null;
  error_message: string | null;
  created_at: string;
  updated_at: string;
};

export type PeriodClosingData = {
  start_date: string;
  end_date: string;
};

export type PeriodClosingResponse = {
  message: string;
  period_closing: PeriodClosing;
};
