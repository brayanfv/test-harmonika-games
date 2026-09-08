import { type ReactNode, useEffect, useState } from 'react';
import api from '../api/client';
import { AuthContext, type RegisterData, type User } from './authContextValue';

export function AuthProvider({ children }: { children: ReactNode }) {
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
    void Promise.resolve().then(fetchUser);
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
