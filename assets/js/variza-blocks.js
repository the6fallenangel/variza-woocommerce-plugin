(() => {
  "use strict";

  const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
  const { getSetting } = window.wc.wcSettings;
  const { decodeEntities } = window.wp.htmlEntities;
  const { createElement } = window.wp.element;

  const settings = getSetting("variza_data", {});
  const title = decodeEntities(settings.title) || "واریزا";
  const icon = settings.icon || "";

  const label = icon
    ? createElement(
        "span",
        {
          style: {
            display: "inline-flex",
            alignItems: "center",
            gap: "8px",
          },
        },
        createElement("img", {
          src: icon,
          alt: "",
          style: {
            width: "22px",
            height: "22px",
            flex: "0 0 22px",
            borderRadius: "6px",
            background: "#ecfdf5",
            padding: "3px",
            objectFit: "contain",
          },
        }),
        createElement("span", { style: { fontWeight: 600 } }, title),
      )
    : title;

  registerPaymentMethod({
    name: "variza",
    label,
    content: createElement("div", null, ""),
    edit: createElement("div", null, ""),
    canMakePayment: () => true,
    ariaLabel: title,
    supports: { features: settings.supports || ["products"] },
  });
})();
