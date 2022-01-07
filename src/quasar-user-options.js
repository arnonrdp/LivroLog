import "@quasar/extras/material-icons/material-icons.css";
import { Notify } from "quasar";
import "./styles/quasar.scss";

export default {
  plugins: { Notify },
  config: {
    notify: {
      position: "top",
      timeout: 2500,
      progress: true,
    },
  },
};
