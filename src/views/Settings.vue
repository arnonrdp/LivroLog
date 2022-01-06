<template>
  <q-page padding class="flex column inline">
    <q-tabs v-model="tab" inline-label active-color="primary" indicator-color="primary" align="justify">
      <q-tab name="account" icon="account_circle" :label="$t('settings.account')" default />
      <q-tab name="books" icon="menu_book" :label="$t('settings.books')" @click="dateOptions" />
    </q-tabs>
    <q-separator />
    <q-tab-panels v-model="tab" animated default>
      <q-tab-panel name="account" default>
        <div class="text-h6">{{ $t("settings.account-profile") }}</div>
        <q-input v-model="shelfName" type="text" :label="$t('book.shelfname')" @keyup.enter="updateShelfName">
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
        <!-- TODO: Incluir botão de salvar para atualizar o nome da estante -->
        <!-- TODO: O botão de salvar também deve armaxenar o idioma preferido -->
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
              <td class="flex items-center no-wrap">
                <q-select v-model="book.readIn.year" :options="yearOptions" dense />
                <q-select v-model="book.readIn.month" :options="monthOptions" v-if="book.readIn?.year" dense />
                <q-select v-model="book.readIn.day" :options="dayOptions" v-if="book.readIn?.month" dense />
                <q-icon v-if="book.readIn?.year" class="cursor-pointer" name="clear" @click="clearDates(book.readIn)" />
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2">
                <q-btn class="q-ma-lg" color="primary" icon="save" label="Save" @click="updateReadDates(books)" />
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
    shelfName: "",
    books: [],
    tab: "account",
    filter: "",
    yearOptions: [],
    monthOptions: [],
    dayOptions: [],
  }),
  components: { Tooltip },
  computed: {
    ...mapGetters(["getUserProfile", "getUserBooks"]),
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
    this.shelfName = this.getUserProfile.shelfName;
    this.books = this.getUserBooks;
    this.dateToYearMonthDay();
  },
  methods: {
    updateShelfName() {
      this.$store.dispatch("updateShelfName", this.shelfName);
    },
    logout() {
      this.$store.dispatch("logout");
    },
    dateToYearMonthDay() {
      this.books.forEach((book) => {
        const date = book.readIn.split("-");
        book.readIn = {
          year: date[0] || "",
          month: date[1] || "",
          day: date[2] || "",
        };
      });
    },
    async updateReadDates(updatedBooks) {
      let updatedFields = [];
      updatedBooks.forEach((book) => {
        updatedFields.push({
          id: book.id,
          readIn: [book.readIn.year, book.readIn.month, book.readIn.day].filter(Boolean).join("-"),
        });
      });
      await this.$store.dispatch("updateReadDates", updatedFields);
      this.$q.notify({ message: this.$t("settings.books-read-date-updated") });
      this.dateToYearMonthDay();
    },
    dateOptions() {
      this.yearOptions = Array.from({ length: 100 }, (v, i) => new Date().getFullYear() - i);
      this.monthOptions = Array.from({ length: 12 }, (v, i) => i + 1);
      this.dayOptions = Array.from({ length: 31 }, (v, i) => i + 1);
    },
    clearDates(obj) {
      obj.year = "";
      obj.month = "";
      obj.day = "";
    },
  },
};
</script>

<style scoped>
.q-page {
  width: 32rem;
}
.q-tab-panels {
  background-color: transparent;
}
</style>
