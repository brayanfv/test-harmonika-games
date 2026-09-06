import { useAuth } from '../../auth/AuthContext';

type HeaderProps = {
  onMenuClick: () => void;
};

export function Header({ onMenuClick }: HeaderProps) {
  const { user } = useAuth();
  const initial = user?.name?.charAt(0).toUpperCase() ?? 'U';

  return (
    <header className="app-header">
      <button
        type="button"
        className="app-header__menu-button"
        aria-label="Abrir menu"
        onClick={onMenuClick}
      >
        <span />
        <span />
        <span />
      </button>

      <div className="app-header__title">
        <span>Visão geral</span>
        <small>Controle Financeiro</small>
      </div>

      <div className="app-header__user" aria-label={`Usuário: ${user?.name ?? 'usuário'}`}>
        <span className="app-header__avatar" aria-hidden="true">
          {initial}
        </span>
        <span className="app-header__user-name">{user?.name ?? 'Usuário'}</span>
      </div>
    </header>
  );
}
