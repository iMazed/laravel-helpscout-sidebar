/*!
 * @helpscout/javascript-sdk 0.10.0, bundled for laravel-helpscout-sidebar.
 *
 * This file is Help Scout's SDK and is not covered by this package's MIT
 * license. It is redistributed unmodified apart from bundling, so that the
 * sidebar iframe makes no request to a third-party CDN at runtime.
 *
 * Source tarball:
 *   https://registry.npmjs.org/@helpscout/javascript-sdk/-/javascript-sdk-0.10.0.tgz
 *   integrity sha512-lTwA6yNsRYwona6VebJhwzZ8zN4pX+orbvTAcsaYbDLEHXOawofNOPkg0Hm35AdRcJ8ms4GZMQI04GhrUxblfg==
 *
 * Rebuilt from that tarball's dist/esm/index.js with:
 *   esbuild --bundle --format=iife --global-name=__helpScoutSidebarSdk
 *
 * Bundles the SDK's one dependency, uuid 9.0.1:
 *   The MIT License (MIT). Copyright (c) 2010-2020 Robert Kieffer and other
 *   contributors. https://github.com/uuidjs/uuid/blob/main/LICENSE.md
 */
var __helpScoutSidebarSdk = (() => {
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // node_modules/@helpscout/javascript-sdk/dist/esm/index.js
  var index_exports = {};
  __export(index_exports, {
    MESSAGE_TYPES: () => MESSAGE_TYPES,
    NOTIFICATION_TYPES: () => NOTIFICATION_TYPES,
    STATUSES: () => STATUSES,
    commsHandler: () => commsHandler,
    default: () => index_default,
    isAllowedOrigin: () => isAllowedOrigin
  });

  // node_modules/uuid/dist/esm-browser/rng.js
  var getRandomValues;
  var rnds8 = new Uint8Array(16);
  function rng() {
    if (!getRandomValues) {
      getRandomValues = typeof crypto !== "undefined" && crypto.getRandomValues && crypto.getRandomValues.bind(crypto);
      if (!getRandomValues) {
        throw new Error("crypto.getRandomValues() not supported. See https://github.com/uuidjs/uuid#getrandomvalues-not-supported");
      }
    }
    return getRandomValues(rnds8);
  }

  // node_modules/uuid/dist/esm-browser/stringify.js
  var byteToHex = [];
  for (let i = 0; i < 256; ++i) {
    byteToHex.push((i + 256).toString(16).slice(1));
  }
  function unsafeStringify(arr, offset = 0) {
    return byteToHex[arr[offset + 0]] + byteToHex[arr[offset + 1]] + byteToHex[arr[offset + 2]] + byteToHex[arr[offset + 3]] + "-" + byteToHex[arr[offset + 4]] + byteToHex[arr[offset + 5]] + "-" + byteToHex[arr[offset + 6]] + byteToHex[arr[offset + 7]] + "-" + byteToHex[arr[offset + 8]] + byteToHex[arr[offset + 9]] + "-" + byteToHex[arr[offset + 10]] + byteToHex[arr[offset + 11]] + byteToHex[arr[offset + 12]] + byteToHex[arr[offset + 13]] + byteToHex[arr[offset + 14]] + byteToHex[arr[offset + 15]];
  }

  // node_modules/uuid/dist/esm-browser/native.js
  var randomUUID = typeof crypto !== "undefined" && crypto.randomUUID && crypto.randomUUID.bind(crypto);
  var native_default = {
    randomUUID
  };

  // node_modules/uuid/dist/esm-browser/v4.js
  function v4(options, buf, offset) {
    if (native_default.randomUUID && !buf && !options) {
      return native_default.randomUUID();
    }
    options = options || {};
    const rnds = options.random || (options.rng || rng)();
    rnds[6] = rnds[6] & 15 | 64;
    rnds[8] = rnds[8] & 63 | 128;
    if (buf) {
      offset = offset || 0;
      for (let i = 0; i < 16; ++i) {
        buf[offset + i] = rnds[i];
      }
      return buf;
    }
    return unsafeStringify(rnds);
  }
  var v4_default = v4;

  // node_modules/@helpscout/javascript-sdk/dist/esm/constants/index.js
  var MESSAGE_TYPES = {
    GET_APP_CONTEXT: "GET_APP_CONTEXT",
    OPEN_SIDE_PANEL: "OPEN_SIDE_PANEL",
    CLOSE_SIDE_PANEL: "CLOSE_SIDE_PANEL",
    SEND_APP_CONTEXT: "SEND_APP_CONTEXT",
    SET_APP_HEIGHT: "SET_APP_HEIGHT",
    SET_CLIPBOARD_TEXT: "SET_CLIPBOARD_TEXT",
    SHOW_NOTIFICATION: "SHOW_NOTIFICATION",
    CONFIRM_NOTIFICATION_CLOSED: "CONFIRM_NOTIFICATION_CLOSED",
    CONFIRM_NOTIFICATION_CONFIRMED: "CONFIRM_NOTIFICATION_CONFIRMED",
    GET_APP_STYLES: "GET_APP_STYLES",
    SEND_APP_STYLES: "SEND_APP_STYLES",
    CLICK_NOTIFICATION_BUTTON: "CLICK_NOTIFICATION_BUTTON"
  };
  var NOTIFICATION_TYPES = {
    CONFIRM: "CONFIRM",
    ERROR: "ERROR",
    MESSAGE: "MESSAGE",
    SUCCESS: "SUCCESS",
    WARNING: "WARNING"
  };
  var STATUSES = {
    ACTIVE: "active",
    PENDING: "pending",
    CLOSED: "closed",
    SPAM: "spam"
  };
  var ALLOWED_ORIGINS = [
    "https://secure.helpscout.net",
    "https://hs-app.*.hsenv.io"
  ];

  // node_modules/@helpscout/javascript-sdk/dist/esm/utils/index.js
  var isAllowedOrigin = (origin) => {
    let parsedOrigin;
    try {
      const url = new URL(origin);
      parsedOrigin = url.origin;
    } catch (error) {
      return false;
    }
    return parsedOrigin && ALLOWED_ORIGINS.some((allowedOrigin) => parsedOrigin.match(allowedOrigin));
  };

  // node_modules/@helpscout/javascript-sdk/dist/esm/handlers/CommunicationHandler.js
  var CommunicationHandler = class {
    send(type, options = {}) {
      if (!isAllowedOrigin(document.referrer)) {
        console.warn("Unable to send message. Please run your app from within Help Scout.");
        return;
      }
      window.parent.postMessage(
        // stringify then parse to remove anything that could cause postMessage errors
        JSON.parse(JSON.stringify({
          ...options,
          type,
          appId: window.name?.replace(/app-side-panel-|app-/, ""),
          iframeId: window.name
        })),
        document.referrer
      );
    }
    async receive(type) {
      return await new Promise((resolve) => {
        window.addEventListener("message", function onMessage(event) {
          if (!isAllowedOrigin(event.origin)) {
            return;
          }
          try {
            const { data } = event;
            const { type: receivingType, ...rest } = data;
            if (receivingType === type) {
              resolve(rest);
            }
          } catch (error) {
            console.error("Unable to read postMessage data.");
          }
        });
      });
    }
    listen(type, callback) {
      window.addEventListener("message", function onMessage(event) {
        if (!isAllowedOrigin(event.origin)) {
          return;
        }
        try {
          const { data } = event;
          const { type: receivingType, ...rest } = data;
          if (receivingType === type) {
            callback(rest);
          }
        } catch (error) {
          console.error("Unable to read postMessage data.");
        }
      });
    }
  };

  // node_modules/@helpscout/javascript-sdk/dist/esm/handlers/index.js
  var commsHandler = new CommunicationHandler();

  // node_modules/@helpscout/javascript-sdk/dist/esm/HelpScout.js
  var HelpScout = class {
    async getApplicationContext() {
      commsHandler.send(MESSAGE_TYPES.GET_APP_CONTEXT);
      return await commsHandler.receive(MESSAGE_TYPES.SEND_APP_CONTEXT);
    }
    watchApplicationContext(callback) {
      commsHandler.listen(MESSAGE_TYPES.SEND_APP_CONTEXT, (data) => {
        callback(data);
      });
    }
    setClipboardText(text, successMessage = "Text copied") {
      commsHandler.send(MESSAGE_TYPES.SET_CLIPBOARD_TEXT, {
        value: { text, successMessage }
      });
    }
    setAppHeight(height) {
      commsHandler.send(MESSAGE_TYPES.SET_APP_HEIGHT, { value: height });
    }
    showNotification(type, text, options = {}) {
      if (!options.id) {
        options.id = v4_default();
      }
      if (type !== NOTIFICATION_TYPES.CONFIRM) {
        const notificationOptions = options;
        const buttonOnClick = notificationOptions?.buttonOptions?.onClick;
        if (buttonOnClick) {
          commsHandler.listen(MESSAGE_TYPES.CLICK_NOTIFICATION_BUTTON, (value) => {
            const message = value;
            if (message.id === options.id) {
              buttonOnClick();
            }
          });
        }
      }
      commsHandler.send(MESSAGE_TYPES.SHOW_NOTIFICATION, {
        value: {
          type,
          message: text,
          options: type === NOTIFICATION_TYPES.CONFIRM ? options : options
        }
      });
      if (type === NOTIFICATION_TYPES.CONFIRM) {
        const confirmNotificationOptions = options;
        if (confirmNotificationOptions.onConfirm) {
          commsHandler.listen(MESSAGE_TYPES.CONFIRM_NOTIFICATION_CONFIRMED, (value) => {
            const message = value;
            if (message.id === confirmNotificationOptions.id && confirmNotificationOptions.onConfirm) {
              confirmNotificationOptions.onConfirm();
            }
          });
        }
        if (confirmNotificationOptions.onClose) {
          commsHandler.listen(MESSAGE_TYPES.CONFIRM_NOTIFICATION_CLOSED, (value) => {
            const message = value;
            if (message.id === confirmNotificationOptions.id && confirmNotificationOptions.onClose) {
              confirmNotificationOptions.onClose();
            }
          });
        }
      }
    }
    async getAppStyles() {
      commsHandler.send(MESSAGE_TYPES.GET_APP_STYLES);
      const { styles } = await commsHandler.receive(MESSAGE_TYPES.SEND_APP_STYLES);
      return styles;
    }
    openSidePanel(contentUrl) {
      commsHandler.send(MESSAGE_TYPES.OPEN_SIDE_PANEL, { value: contentUrl });
    }
    closeSidePanel() {
      commsHandler.send(MESSAGE_TYPES.CLOSE_SIDE_PANEL);
    }
    getInstallationIds() {
      const name = window?.name;
      return name && name.startsWith("app-") ? name.replace("app-", "").split("-").filter((item) => !!item) : [];
    }
  };

  // node_modules/@helpscout/javascript-sdk/dist/esm/index.js
  var index_default = new HelpScout();
  return __toCommonJS(index_exports);
})();
