import "@quasar/extras/material-icons/material-icons.css";
import { Meta, Notify } from "quasar";
import "./styles/quasar.scss";

export default {
  plugins: { Notify, Meta },
  config: {
    notify: {
      html: true,
      position: "top",
      progress: true,
      timeout: 3000,
      multiline: true,
    },
  },
};
