/**
 * NEXUS API Response Validation Helper
 *
 * Provides a `validateResponse` function that validates API responses against
 * Zod schemas in development mode only. In production, validation is completely
 * skipped (zero overhead).
 *
 * This is a DIAGNOSTIC tool — it never throws or breaks the app. It only logs
 * console warnings when validation fails, helping developers catch API contract
 * mismatches early.
 */

import type { z } from 'zod';

/**
 * Validate an API response against a Zod schema.
 *
 * - In development: parses the response and logs warnings on failure.
 * - In production: does nothing (zero overhead, Zod not even imported).
 *
 * @param schema  - Zod schema to validate against
 * @param data    - The response data to validate
 * @param context - Human-readable context string (e.g. endpoint URL or description)
 * @returns The data as-is (validation is purely diagnostic, never transforms)
 */
export function validateResponse<T>(
  schema: z.ZodType<T>,
  data: unknown,
  context: string
): typeof data {
  // Only validate in development — production has zero overhead
  if (!import.meta.env.DEV) {
    return data;
  }

  try {
    const result = schema.safeParse(data);

    if (!result.success) {
      // Group the warning for cleaner console output
      console.groupCollapsed(
        `%c[API Schema] Validation warning: ${context}`,
        'color: #f59e0b; font-weight: bold;'
      );
      console.warn('Schema validation errors:');

      for (const issue of result.error.issues) {
        const path = issue.path.length > 0 ? issue.path.join('.') : '(root)';
        console.warn(`  - ${path}: ${issue.message} (${issue.code})`);
      }

      console.warn('Received data:', data);
      console.groupEnd();
    }
  } catch {
    // If validation itself errors (shouldn't happen), silently ignore.
    // This function must NEVER break the app.
  }

  return data;
}

/**
 * Convenience: validate only if data is not null/undefined.
 * Useful for optional response bodies where null is a valid "no data" case.
 */
export function validateResponseIfPresent<T>(
  schema: z.ZodType<T>,
  data: unknown,
  context: string
): typeof data {
  if (data == null) {
    return data;
  }
  return validateResponse(schema, data, context);
}
