// Google Identity Services utilities
export interface GoogleAuthCredential {
  credential: string
  select_by: string
}

export interface GoogleUserInfo {
  id: string
  email: string
  name: string
  picture?: string
  email_verified: boolean
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

  static async initialize(): Promise<void> {
    if (this.initialized) return

    return new Promise((resolve, reject) => {
      if (!this.CLIENT_ID) {
        reject(new Error('Google Client ID not configured'))
        return
      }

      // Load Google Identity Services script
      if (!document.querySelector('script[src*="accounts.google.com"]')) {
        const script = document.createElement('script')
        script.src = 'https://accounts.google.com/gsi/client'
        script.async = true
        script.defer = true

        script.onload = () => {
          this.initializeGoogleAuth()
          this.initialized = true
          resolve()
        }

        script.onerror = () => {
          reject(new Error('Failed to load Google Identity Services'))
        }

        document.head.appendChild(script)
      } else {
        this.initializeGoogleAuth()
        this.initialized = true
        resolve()
      }
    })
  }

  private static initializeGoogleAuth(): void {
    if (!window.google?.accounts?.id) {
      throw new Error('Google Identity Services not loaded')
    }

    if (!this.CLIENT_ID) {
      throw new Error('Google Client ID not configured')
    }
  }

  static renderSignInButton(
    element: HTMLElement,
    callback: (idToken: string) => void,
    options: {
      theme?: 'outline' | 'filled_blue' | 'filled_black'
      size?: 'large' | 'medium' | 'small'
      text?: 'sign_in_with' | 'sign_up_with' | 'continue_with' | 'sign_in'
      shape?: 'rectangular' | 'pill' | 'circle' | 'square'
      width?: string
    } = {}
  ): void {
    if (!this.initialized) {
      throw new Error('GoogleAuth not initialized. Call initialize() first.')
    }

    window.google.accounts.id.initialize({
      client_id: this.CLIENT_ID,
      callback: (response: GoogleAuthCredential) => {
        callback(response.credential)
      },
      auto_select: false,
      cancel_on_tap_outside: false
    })

    window.google.accounts.id.renderButton(element, {
      theme: options.theme || 'outline',
      size: options.size || 'large',
      text: options.text || 'continue_with',
      shape: options.shape || 'rectangular',
      width: options.width
    })
  }

  static async signIn(): Promise<string> {
    return new Promise((resolve, reject) => {
      if (!this.initialized) {
        reject(new Error('GoogleAuth not initialized. Call initialize() first.'))
        return
      }

      window.google.accounts.id.initialize({
        client_id: this.CLIENT_ID,
        callback: (response: GoogleAuthCredential) => {
          resolve(response.credential)
        },
        auto_select: false,
        cancel_on_tap_outside: false
      })

      // Trigger the sign-in prompt
      window.google.accounts.id.prompt()
    })
  }

  static decodeIdToken(idToken: string): GoogleUserInfo {
    try {
      // Decode JWT payload (base64url)
      const payload = idToken.split('.')[1]
      const decodedPayload = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))
      const userInfo = JSON.parse(decodedPayload)

      return {
        id: userInfo.sub,
        email: userInfo.email,
        name: userInfo.name,
        picture: userInfo.picture,
        email_verified: userInfo.email_verified || false
      }
    } catch (error) {
      throw new Error('Failed to decode Google ID token')
    }
  }

  static disableAutoSelect(): void {
    if (this.initialized && window.google?.accounts?.id) {
      window.google.accounts.id.disableAutoSelect()
    }
  }
}
