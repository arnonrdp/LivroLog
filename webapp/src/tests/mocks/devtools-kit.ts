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

export default {
  devtools,
  setupDevtoolsPlugin,
  addCustomTab,
  addCustomCommand,
  removeCustomCommand
}
