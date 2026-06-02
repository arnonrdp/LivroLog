// Global setup that runs before environment initialization
// This is a workaround for @vue/devtools-kit localStorage issues
export default async function globalSetup() {
  const nodeProcess = (globalThis as { process?: { env?: Record<string, string | undefined> } }).process

  // Set environment to test to disable some devtools features
  if (nodeProcess?.env) {
    nodeProcess.env.NODE_ENV = 'test'
  }
}
