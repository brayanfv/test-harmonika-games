import { useAuth } from '../auth/AuthContext';

export function DashboardPage() {
  const { user } = useAuth();

  return (
    <section className="dashboard" aria-labelledby="dashboard-title">
      <div className="dashboard__intro">
        <span className="dashboard__eyebrow">Controle financeiro</span>
        <h1 id="dashboard-title">Olá, {user?.name?.split(' ')[0] ?? 'usuário'}.</h1>
        <p>Seu espaço de gestão está pronto para acompanhar sua operação.</p>
      </div>

      <div className="dashboard__placeholder">
        <span className="dashboard__placeholder-icon" aria-hidden="true">
          ✦
        </span>
        <h2>Visão geral em breve</h2>
        <p>
          Aqui você encontrará um resumo claro das suas movimentações e contatos.
        </p>
      </div>
    </section>
  );
}
