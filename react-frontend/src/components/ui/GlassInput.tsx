import { forwardRef, type InputHTMLAttributes } from 'react';

export interface GlassInputProps extends InputHTMLAttributes<HTMLInputElement> {
  /** Label text displayed above input */
  label?: string;
  /** Error message to display */
  error?: string;
  /** Helper text displayed below input */
  helperText?: string;
}

/**
 * GlassInput - Glassmorphism input component
 *
 * Uses centralized CSS utilities from styles/glass.css
 */
export const GlassInput = forwardRef<HTMLInputElement, GlassInputProps>(
  ({ label, error, helperText, className = '', id, disabled, ...props }, ref) => {
    const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
    const errorClass = error ? 'glass-input-error' : '';
    const combinedClassName = ['glass-input', errorClass, className].filter(Boolean).join(' ');

    return (
      <div className="w-full">
        {label && (
          <label
            htmlFor={inputId}
            className="block text-sm font-medium mb-2"
            style={{ color: 'var(--foreground-muted)' }}
          >
            {label}
          </label>
        )}
        <input
          ref={ref}
          id={inputId}
          className={combinedClassName}
          disabled={disabled}
          aria-invalid={!!error}
          aria-describedby={error ? `${inputId}-error` : helperText ? `${inputId}-helper` : undefined}
          {...props}
        />
        {error && (
          <p
            id={`${inputId}-error`}
            className="mt-2 text-sm"
            style={{ color: '#ef4444' }}
            role="alert"
          >
            {error}
          </p>
        )}
        {helperText && !error && (
          <p
            id={`${inputId}-helper`}
            className="mt-2 text-sm"
            style={{ color: 'var(--foreground-subtle)' }}
          >
            {helperText}
          </p>
        )}
      </div>
    );
  }
);

GlassInput.displayName = 'GlassInput';

export default GlassInput;
