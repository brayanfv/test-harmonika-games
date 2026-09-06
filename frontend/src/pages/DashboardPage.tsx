import { useAuth } from '../auth/AuthContext';

export function DashboardPage() {
  const { user, logout } = useAuth();

  return (
    <main>
      <h1>Controle Financeiro</h1>

      <p>Olá, {user?.name}.</p>

      <p>Você está autenticado.</p>

      <button type="button" onClick={logout}>
        Sair
      </button>
    </main>
  );
}