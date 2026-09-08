import { useEffect, useId, useRef } from 'react';

type ConfirmationModalProps = {
  isOpen: boolean;
  title: string;
  message: string;
  confirmText: string;
  cancelText?: string;
  variant?: 'accent' | 'danger';
  busy?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
};

export function ConfirmationModal({
  isOpen,
  title,
  message,
  confirmText,
  cancelText = 'Cancelar',
  variant = 'accent',
  busy = false,
  onConfirm,
  onCancel,
}: ConfirmationModalProps) {
  const titleId = useId();
  const messageId = useId();
  const dialogRef = useRef<HTMLDivElement>(null);
  const cancelButtonRef = useRef<HTMLButtonElement>(null);
  const busyRef = useRef(busy);
  const onCancelRef = useRef(onCancel);

  useEffect(() => {
    busyRef.current = busy;
    onCancelRef.current = onCancel;
  }, [busy, onCancel]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const previousFocus = document.activeElement as HTMLElement | null;
    cancelButtonRef.current?.focus();

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Escape' && !busyRef.current) {
        onCancelRef.current();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusableElements = dialogRef.current?.querySelectorAll<HTMLButtonElement>(
        'button:not(:disabled)',
      );

      if (!focusableElements?.length) {
        return;
      }

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
      } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
      }
    }

    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);

      if (previousFocus?.isConnected) {
        previousFocus.focus();
      } else {
        document.querySelector<HTMLElement>('main button:not(:disabled), main a[href], main input')?.focus();
      }
    };
  }, [isOpen]);

  if (!isOpen) {
    return null;
  }

  return (
    <div
      className="confirmation-modal__backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) {
          onCancel();
        }
      }}
    >
      <div
        ref={dialogRef}
        className="confirmation-modal"
        role="dialog"
        aria-modal="true"
        aria-busy={busy}
        aria-labelledby={titleId}
        aria-describedby={messageId}
      >
        <span className={`confirmation-modal__icon confirmation-modal__icon--${variant}`} aria-hidden="true">
          {variant === 'danger' ? '!' : '✓'}
        </span>
        <h2 id={titleId}>{title}</h2>
        <p id={messageId}>{message}</p>

        <div className="confirmation-modal__actions">
          <button
            ref={cancelButtonRef}
            type="button"
            className="confirmation-modal__cancel"
            disabled={busy}
            onClick={onCancel}
          >
            {cancelText}
          </button>
          <button
            type="button"
            className={`confirmation-modal__confirm confirmation-modal__confirm--${variant}`}
            disabled={busy}
            onClick={onConfirm}
          >
            {confirmText}
          </button>
        </div>
      </div>
    </div>
  );
}
