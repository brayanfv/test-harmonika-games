import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from './useAuth';

export function ProtectedRoute() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <main className="auth-loading" role="status" aria-live="polite">
        <div className="auth-loading__brand" aria-label="Harmonika Controle Financeiro">
          <span className="auth-loading__mark" aria-hidden="true">H</span>
          <strong>Harmonika</strong>
          <small>Controle Financeiro</small>
        </div>
        <span className="auth-loading__spinner" aria-hidden="true" />
        <span className="auth-loading__text">Carregando sua conta...</span>
      </main>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  return <Outlet />;
}
