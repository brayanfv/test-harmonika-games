import api from './client';
import type { Transaction, TransactionData } from '../types/transaction';

export async function getTransactions(): Promise<Transaction[]> {
  const response = await api.get<Transaction[]>('/api/transactions');

  return response.data;
}

export async function createTransaction(data: TransactionData): Promise<Transaction> {
  const response = await api.post<Transaction>('/api/transactions', data);

  return response.data;
}

export async function updateTransaction(id: number, data: TransactionData): Promise<Transaction> {
  const response = await api.put<Transaction>(`/api/transactions/${id}`, data);

  return response.data;
}

export async function deleteTransaction(id: number): Promise<void> {
  await api.delete(`/api/transactions/${id}`);
}
