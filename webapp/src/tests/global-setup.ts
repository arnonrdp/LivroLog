// Global setup that runs before environment initialization
// This is a workaround for @vue/devtools-kit localStorage issues
export default async function globalSetup() {
  // Set environment to test to disable some devtools features
  process.env.NODE_ENV = 'test'
}
