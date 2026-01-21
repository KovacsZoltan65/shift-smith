export function csrfToken() {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content") ?? ""
    );
}

export async function csrfFetch(url, options = {}) {
    const xsrf = decodeURIComponent(getCookie("XSRF-TOKEN"));

    return fetch(url, {
        credentials: "include",
        ...options,
        headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
            ...(xsrf ? { "X-XSRF-TOKEN": xsrf } : {}),
            ...(options.headers ?? {}),
        },
    });
}

function getCookie(name) {
    const m = document.cookie.match(
        new RegExp(
            "(?:^|; )" +
                name.replace(/[$()*+./?[\\\]^{|}-]/g, "\\$&") +
                "=([^;]*)",
        ),
    );
    return m ? m[1] : "";
}
