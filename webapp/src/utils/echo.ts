import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { LocalStorage } from 'quasar'

// Make Pusher available globally for Laravel Echo
declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo<'reverb'>
  }
}

window.Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null

export function getEcho(): Echo<'reverb'> | null {
  return echoInstance
}

export function initEcho(): Echo<'reverb'> | null {
  // Don't reinitialize if already exists
  if (echoInstance) {
    return echoInstance
  }

  const token = LocalStorage.getItem('access_token')

  // Don't initialize if no auth token
  if (!token) {
    return null
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${import.meta.env.VITE_API_URL}/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json'
      }
    }
  })

  window.Echo = echoInstance
  return echoInstance
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

export function reconnectEcho(): Echo<'reverb'> | null {
  disconnectEcho()
  return initEcho()
}
