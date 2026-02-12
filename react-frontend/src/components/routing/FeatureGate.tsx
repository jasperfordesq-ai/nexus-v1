/**
 * Feature Gate Component
 * Conditionally renders content based on tenant feature flags or module flags
 *
 * Supports two modes:
 * - feature: checks TenantFeatures (optional add-ons like gamification, goals)
 * - module: checks TenantModules (core modules like listings, wallet, messages)
 *
 * Provide one of feature or module (not both).
 */

import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useTenant } from '@/contexts';
import type { TenantFeatures, TenantModules } from '@/types';

interface FeatureGateProps {
  /**
   * The feature flag to check (optional add-on features)
   */
  feature?: keyof TenantFeatures;

  /**
   * The module flag to check (core platform modules)
   */
  module?: keyof TenantModules;

  /**
   * Content to render if feature/module is enabled
   */
  children: ReactNode;

  /**
   * Content to render if feature/module is disabled (optional)
   * If not provided and redirect is not set, nothing is rendered
   */
  fallback?: ReactNode;

  /**
   * Path to redirect to if feature/module is disabled (optional)
   * Takes precedence over fallback
   */
  redirect?: string;
}

export function FeatureGate({
  feature,
  module,
  children,
  fallback = null,
  redirect,
}: FeatureGateProps) {
  const { hasFeature, hasModule, isLoading, tenantPath } = useTenant();

  // While loading tenant config, show children (assume enabled by default)
  // to avoid layout flash — the gate will re-evaluate once config loads
  if (isLoading) {
    return <>{children}</>;
  }

  // Check if feature or module is enabled
  const isEnabled = feature
    ? hasFeature(feature)
    : module
      ? hasModule(module)
      : true;

  if (!isEnabled) {
    if (redirect) {
      return <Navigate to={tenantPath(redirect)} replace />;
    }
    return <>{fallback}</>;
  }

  return <>{children}</>;
}

/**
 * Higher-order component version of FeatureGate
 */
export function withFeatureGate<P extends object>(
  Component: React.ComponentType<P>,
  feature: keyof TenantFeatures,
  options?: { fallback?: ReactNode; redirect?: string }
) {
  return function WrappedComponent(props: P) {
    return (
      <FeatureGate
        feature={feature}
        fallback={options?.fallback}
        redirect={options?.redirect}
      >
        <Component {...props} />
      </FeatureGate>
    );
  };
}

export default FeatureGate;
