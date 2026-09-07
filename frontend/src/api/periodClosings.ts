import api from './client';
import type {
  PeriodClosing,
  PeriodClosingData,
  PeriodClosingResponse,
} from '../types/periodClosing';

export async function requestPeriodClosing(
  data: PeriodClosingData,
): Promise<PeriodClosingResponse> {
  const response = await api.post<PeriodClosingResponse>('/api/period-closings', data);

  return response.data;
}

export async function getPeriodClosing(id: number): Promise<PeriodClosing> {
  const response = await api.get<PeriodClosing>(`/api/period-closings/${id}`);

  return response.data;
}
