<template>
  <div class="text-h6">{{ $t("settings.books") }}</div>
  <p>{{ $t("settings.books-description") }}</p>
  <p v-if="books?.length == 0">
    {{ $t("settings.bookshelf-empty") }}
    <router-link to="/add">{{ $t("settings.bookshelf-add-few") }}</router-link>
  </p>
  <table v-else class="q-mx-auto">
    <thead>
      <tr>
        <th>{{ $t("settings.column-title") }}</th>
        <th>{{ $t("settings.column-readIn") }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="book in books" :key="book.id">
        <td class="text-left">{{ book.title }}</td>
        <td class="input-date">
          <q-input dense v-model="book.readIn" mask="####-##-##" :rules="['YYYY-MM-DD']">
            <template v-slot:append>
              <q-icon name="event" class="cursor-pointer">
                <q-popup-proxy ref="qDateProxy" cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="book.readIn" mask="YYYY-MM-DD" minimal />
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </td>
      </tr>
    </tbody>
  </table>
  <br />
  <q-btn color="primary" icon="save" :loading="saving" :label="$t('settings.save')" @click="updateReadDates(books)" />
</template>

<script>
import { useMeta } from 'quasar';
import { useI18n } from 'vue-i18n';
import { mapGetters } from "vuex";

export default {
  setup() {
    const { t } = useI18n();
    useMeta({
      title: `Livrero | ${t("settings.books")}`,
      meta: {
        ogTitle: { name: "og:title", content: `Livrero | ${t("settings.books")}` },
        twitterTitle: { name: "twitter:title", content: `Livrero | ${t("settings.books")}` },
      },
    });
  },
  data: () => ({
    books: [],
    saving: false,
  }),
  computed: {
    ...mapGetters(["getMyBooks"]),
  },
  mounted() {
    this.books = this.getMyBooks;
  },
  methods: {
    async updateReadDates(updatedBooks) {
      this.saving = true;
      let updatedFields = [];
      for (const book of updatedBooks) {
        updatedFields.push({ id: book.id, readIn: book.readIn });
      }
      await this.$store
        .dispatch("updateReadDates", updatedFields)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.read-dates-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.read-dates-updated-error") }))
        .finally(() => (this.saving = false));
    },
  },
};
</script>

<style scoped>
.input-date,
.input-date > label {
  min-width: 70px;
  padding-bottom: 0;
}
</style>
