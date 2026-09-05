import { createContext, useContext, useEffect, useState } from 'react';
import api from '../api/client';

type User = {
  id: number;
  name: string;
  email: string;
};

type RegisterData = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
};

type AuthContextType = {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  register: (data: RegisterData) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  async function fetchUser() {
    try {
      const response = await api.get<User>('/api/user');
      setUser(response.data);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }

  async function login(email: string, password: string) {
    await api.get('/sanctum/csrf-cookie');

    await api.post('/api/login', {
      email,
      password,
    });

    await fetchUser();
  }

  async function register(data: RegisterData) {
    await api.get('/sanctum/csrf-cookie');

    await api.post('/api/register', data);

    await fetchUser();
  }

  async function logout() {
    await api.post('/api/logout');
    setUser(null);
  }

  useEffect(() => {
    fetchUser();
  }, []);

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        register,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}