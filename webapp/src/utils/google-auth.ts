// Google Identity Services utilities
import { jwtDecode } from 'jwt-decode'
import { loadScript } from './loadScript'

export interface GoogleAuthCredential {
  credential: string
  select_by: string
}

export interface GoogleUserInfo {
  id: string
  email: string
  name: string
  picture: string
  email_verified: boolean
}

interface JwtPayload {
  sub: string
  email: string
  name: string
  picture?: string
  email_verified?: boolean
}

declare global {
  interface Window {
    google: {
      accounts: {
        id: {
          initialize: (config: {
            client_id: string
            callback: (response: GoogleAuthCredential) => void
            auto_select?: boolean
            cancel_on_tap_outside?: boolean
          }) => void
          renderButton: (
            element: HTMLElement,
            config: {
              theme?: 'outline' | 'filled_blue' | 'filled_black'
              size?: 'large' | 'medium' | 'small'
              text?: 'sign_in_with' | 'sign_up_with' | 'continue_with' | 'sign_in'
              shape?: 'rectangular' | 'pill' | 'circle' | 'square'
              logo_alignment?: 'left' | 'center'
              width?: string
            }
          ) => void
          prompt: () => void
          disableAutoSelect: () => void
        }
      }
    }
  }
}

export class GoogleAuth {
  private static readonly CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID as string
  private static initialized = false

  private static async ensureInitialized(): Promise<void> {
    if (this.initialized) return
    if (!this.CLIENT_ID) throw new Error('Google Client ID not configured')
    await loadScript('https://accounts.google.com/gsi/client')
    this.initialized = true
  }

  private static initRequest(callback: (r: GoogleAuthCredential) => void): void {
    window.google.accounts.id.initialize({
      client_id: this.CLIENT_ID,
      callback,
      auto_select: false,
      cancel_on_tap_outside: false
    })
  }

  static async renderSignInButton(
    element: HTMLElement,
    callback: (idToken: string) => void,
    options: {
      theme?: 'outline' | 'filled_blue' | 'filled_black'
      size?: 'large' | 'medium' | 'small'
      text?: 'sign_in_with' | 'sign_up_with' | 'continue_with' | 'sign_in'
      shape?: 'rectangular' | 'pill' | 'circle' | 'square'
      width?: string
    } = {}
  ): Promise<void> {
    await this.ensureInitialized()
    this.initRequest((r) => callback(r.credential))

    const buttonConfig: Parameters<typeof window.google.accounts.id.renderButton>[1] = {
      theme: options.theme || 'outline',
      size: options.size || 'large',
      text: options.text || 'continue_with',
      shape: options.shape || 'rectangular'
    }

    if (options.width) {
      buttonConfig.width = options.width
    }

    window.google.accounts.id.renderButton(element, buttonConfig)
  }

  static async signIn(): Promise<string> {
    await this.ensureInitialized()
    return new Promise((resolve) => {
      this.initRequest((r) => resolve(r.credential))
      window.google.accounts.id.prompt()
    })
  }

  static decodeIdToken(idToken: string): GoogleUserInfo {
    try {
      const { sub: id, email, name, picture, email_verified = false } = jwtDecode<JwtPayload>(idToken)

      return { id, email, name, picture: picture || '', email_verified }
    } catch (error) {
      console.error('Failed to decode Google ID token:', error)
      throw new Error(`Failed to decode Google ID token: ${error instanceof Error ? error.message : 'Unknown error'}`)
    }
  }

  static disableAutoSelect(): void {
    if (this.initialized && window.google?.accounts?.id) {
      window.google.accounts.id.disableAutoSelect()
    }
  }
}
