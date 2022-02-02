<template>
  <q-layout>
    <Header />
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script>
import Header from "@/components/TheHeader.vue";
import { useMeta } from "quasar";
import { useI18n } from "vue-i18n";
import { ref } from "vue";

export default {
  components: { Header },
  setup() {
    const { t } = useI18n();
    const description = ref("");
    const keywords = ref("");
    const ogDescription = ref("");
    const twitterDescription = ref("");
    useMeta(() => ({
      title: "Livrero",
      meta: {
        // Primary Meta Tags
        description: description.value,
        keywords: keywords.value,
        author: { name: "author", content: "Arnon Rodrigues" },
        // Open Graph / Facebook
        ogType: { name: "og:type", content: "website" },
        ogTitle: { name: "og:title", content: "Livrero" },
        ogDescription: ogDescription.value,
        ogImage: { name: "og:image", content: "https://livrero.vercel.app/main.jpg" },
        ogUrl: { name: "og:url", content: "https://livrero.vercel.app/" },
        // Twitter
        twitterCard: { name: "twitter:card", content: "summary_large_image" },
        twitterTitle: { name: "twitter:title", content: "Livrero" },
        twitterDescription: twitterDescription.value,
        twitterImage: { name: "twitter:image", content: "https://livrero.vercel.app/main.jpg" },
        twitterUrl: { name: "twitter:url", content: "https://livrero.vercel.app/" },
      },
    }));
    function setLanguage() {
      description.value = t("meta.description");
      keywords.value = { name: "keywords", content: t("meta.keywords") };
      ogDescription.value = { name: "og:description", content: t("meta.description") };
      twitterDescription.value = { name: "twitter:description", content: t("meta.description") };
    }
    return {
      setLanguage,
    };
  },
};
</script>
