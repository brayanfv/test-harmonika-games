import { createContext } from 'react';

export type User = {
  id: number;
  name: string;
  email: string;
};

export type RegisterData = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

export type AuthContextType = {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => Promise<void>;
};

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
