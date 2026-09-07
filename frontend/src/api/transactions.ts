import api from './client';
import type { Transaction } from '../types/transaction';

export async function getTransactions(): Promise<Transaction[]> {
  const response = await api.get<Transaction[]>('/api/transactions');

  return response.data;
}
