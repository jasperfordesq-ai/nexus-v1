/**
 * Feature Gate Component
 * Conditionally renders content based on tenant feature flags
 */

import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useTenant } from '@/contexts';
import type { TenantFeatures } from '@/types';

interface FeatureGateProps {
  /**
   * The feature flag to check
   */
  feature: keyof TenantFeatures;

  /**
   * Content to render if feature is enabled
   */
  children: ReactNode;

  /**
   * Content to render if feature is disabled (optional)
   * If not provided and redirect is not set, nothing is rendered
   */
  fallback?: ReactNode;

  /**
   * Path to redirect to if feature is disabled (optional)
   * Takes precedence over fallback
   */
  redirect?: string;
}

export function FeatureGate({
  feature,
  children,
  fallback = null,
  redirect,
}: FeatureGateProps) {
  const { hasFeature, isLoading } = useTenant();

  // While loading tenant config, render nothing or children
  // (children might have their own loading state)
  if (isLoading) {
    return null;
  }

  // Check if feature is enabled
  if (!hasFeature(feature)) {
    if (redirect) {
      return <Navigate to={redirect} replace />;
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
