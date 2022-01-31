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

export default {
  components: { Header },
  setup() {
    const { t } = useI18n();
    useMeta({
      meta: {
        description: { name: "description", content: t("meta.description") },
        keywords: { name: "keywords", content: t("meta.keywords") },
        ogDescription: { name: "og:description", content: t("meta.description") },
        twitterDescription: { name: "twitter:description", content: t("meta.description") },
      },
    });
  },
};
</script>
