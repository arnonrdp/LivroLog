// Mock for @vue/devtools-kit
export const devtools = {
  hook: {
    on: () => {},
    emit: () => {}
  }
}

export const setupDevtoolsPlugin = () => {}
export const addCustomTab = () => {}
export const addCustomCommand = () => {}
export const removeCustomCommand = () => {}

// Re-exported by @vue/devtools-api v8, which pinia 4 imports unconditionally
export const setupDevToolsPlugin = () => {}
export const onDevToolsClientConnected = () => {}
export const onDevToolsConnected = () => {}

export default {
  devtools,
  setupDevtoolsPlugin,
  addCustomTab,
  addCustomCommand,
  removeCustomCommand,
  setupDevToolsPlugin,
  onDevToolsClientConnected,
  onDevToolsConnected
}
