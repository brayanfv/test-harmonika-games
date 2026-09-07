import api from './client';
import type { Contact, ContactData } from '../types/contact';

export async function getContacts(): Promise<Contact[]> {
  const response = await api.get<Contact[]>('/api/contacts');
  return response.data;
}

export async function createContact(data: ContactData): Promise<Contact> {
  const response = await api.post<Contact>('/api/contacts', data);
  return response.data;
}

export async function updateContact(id: number, data: ContactData): Promise<Contact> {
  const response = await api.put<Contact>(`/api/contacts/${id}`, data);
  return response.data;
}

export async function deleteContact(id: number): Promise<void> {
  await api.delete(`/api/contacts/${id}`);
}
