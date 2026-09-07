export type TransactionType = 'payable' | 'receivable';

export type TransactionStatus = 'pending' | 'paid';

export type TransactionContact = {
  id: number;
  user_id: number;
  name: string;
  email: string | null;
  phone: string | null;
  created_at: string;
  updated_at: string;
};

export type Transaction = {
  id: number;
  user_id: number;
  contact_id: number | null;
  type: TransactionType;
  description: string;
  amount: string;
  due_date: string;
  status: TransactionStatus;
  paid_at: string | null;
  created_at: string;
  updated_at: string;
  contact: TransactionContact | null;
};

export type TransactionData = {
  contact_id: number | null;
  type: TransactionType;
  description: string;
  amount: number;
  due_date: string;
};
