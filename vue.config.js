module.exports = {
  pluginOptions: {
    quasar: {
      importStrategy: "kebab",
      rtlSupport: false,
    },
  },
  transpileDependencies: ["quasar"],
  css: {
    loaderOptions: {
      sass: {
        sassOptions: {
          quietDeps: true,
        },
      },
    },
  },
  performance: {
    maxAssetSize: 512000,
    maxEntrypointSize: 512000,
  },
};
