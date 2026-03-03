/**
 * API Configuration
 * 
 * Set VITE_API_BASE_URL in your environment or .env file to point
 * to your PHP backend, e.g.:
 *   VITE_API_BASE_URL=https://yourdomain.com/ai-noc/api
 * 
 * If not set, falls back to mock data.
 */
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL as string | undefined;

export const isLiveMode = (): boolean => !!API_BASE_URL;
