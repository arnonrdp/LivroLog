<template>
  <q-page padding :style-fn="myTweek">
    <q-tabs v-model="tab" inline-label active-color="primary" indicator-color="primary" align="justify">
      <q-tab name="account" icon="account_circle" :label="$t('settings.account')" default />
      <q-tab name="books" icon="menu_book" :label="$t('settings.books')" />
    </q-tabs>
    <q-separator />
    <q-tab-panels v-model="tab" animated default>
      <q-tab-panel name="account" default>
        <div class="text-h6">{{ $t("settings.account-profile") }}</div>
        <q-input v-model="shelfName" :label="$t('book.shelfname')">
          <template v-slot:prepend>
            <q-icon name="badge" />
          </template>
        </q-input>
        <q-select v-model="locale" :options="localeOptions" :label="$t('settings.language')" emit-value map-options>
          <template v-slot:prepend>
            <q-icon name="translate" />
          </template>
        </q-select>
        <br />
        <q-btn color="primary" icon="save" :label="$t('settings.save')" @click="updateShelfName" />
        <q-btn flat color="primary" icon="logout" :label="$t('sign.logout')" @click="logout" />
      </q-tab-panel>
      <q-tab-panel name="books">
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
              <td class="flex items-center no-wrap" style="max-width: 130px">
                <!-- TODO: Verificar por que este input não é exibido no mobile -->
                <q-input v-model="book.readIn" dense input-class="text-right" type="date" />
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2">
                <q-btn
                  class="q-ma-lg"
                  color="primary"
                  icon="save"
                  :loading="saving"
                  :label="$t('settings.save')"
                  @click="updateReadDates(books)"
                />
              </td>
            </tr>
          </tfoot>
        </table>
      </q-tab-panel>
    </q-tab-panels>
  </q-page>
</template>

<script>
import Tooltip from "@adamdehaven/vue-custom-tooltip";
import { useI18n } from "vue-i18n";
import { mapGetters } from "vuex";

export default {
  name: "Settings",
  data: () => ({
    books: [],
    offset: 115,
    saving: false,
    shelfName: "",
    tab: "account",
  }),
  components: { Tooltip },
  computed: {
    ...mapGetters(["getMyShelfName", "getMyBooks"]),
  },
  setup() {
    const { locale } = useI18n({ useScope: "global" });
    return {
      locale,
      localeOptions: [
        { value: "en", label: "English" },
        { value: "ja", label: "日本語" },
        { value: "pt" && "pt-BR", label: "Português" },
      ],
    };
  },
  mounted() {
    this.shelfName = this.getMyShelfName;
    this.books = this.getMyBooks;
  },
  methods: {
    myTweak() {
      return { minHeight: `calc(100vh - ${this.offset}px)` };
    },
    updateShelfName() {
      this.$store.dispatch("updateShelfName", this.shelfName)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.shelfname-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.shelfname-updated-error") }));
    },
    logout() {
      this.$store.dispatch("logout");
    },
    async updateReadDates(updatedBooks) {
      this.saving = true;
      let updatedFields = [];
      for (const book of updatedBooks) {
        updatedFields.push({ id: book.id, readIn: book.readIn });
      }
      await this.$store.dispatch("updateReadDates", updatedFields)
        .then(() => this.$q.notify({ icon: "check_circle", message: this.$t("settings.read-dates-updated") }))
        .catch(() => this.$q.notify({ icon: "error", message: this.$t("settings.read-dates-updated-error") }))
        .finally(() => this.saving = false);
    },
  },
};
</script>

<style scoped>
.q-page {
  margin: 0 auto;
  width: 32rem;
}
.q-tab-panels {
  background-color: transparent;
}
</style>
