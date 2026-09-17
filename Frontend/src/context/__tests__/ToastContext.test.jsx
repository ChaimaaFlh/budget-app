import { describe, it, expect, vi } from "vitest";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ToastProvider, useToast } from "../ToastContext";

function Probe() {
  const { showToast } = useToast();
  return (
    <div>
      <button onClick={() => showToast("Sauvegardé !")}>succes</button>
      <button onClick={() => showToast("Erreur serveur", true)}>erreur</button>
    </div>
  );
}

describe("ToastContext", () => {
  it("affiche un toast de succès sans classe 'error'", async () => {
    render(<ToastProvider><Probe /></ToastProvider>);
    const user = userEvent.setup();
    await user.click(screen.getByText("succes"));

    const toast = await screen.findByText("Sauvegardé !");
    expect(toast.parentElement).toHaveClass("show");
    expect(toast.parentElement).not.toHaveClass("error");
  });

  it("affiche un toast d'erreur avec la classe 'error'", async () => {
    render(<ToastProvider><Probe /></ToastProvider>);
    const user = userEvent.setup();
    await user.click(screen.getByText("erreur"));

    const toast = await screen.findByText("Erreur serveur");
    expect(toast.parentElement).toHaveClass("error");
  });

  it("remplace un toast en cours par le nouveau", async () => {
    render(<ToastProvider><Probe /></ToastProvider>);
    const user = userEvent.setup();
    await user.click(screen.getByText("succes"));
    await screen.findByText("Sauvegardé !");
    await user.click(screen.getByText("erreur"));

    expect(await screen.findByText("Erreur serveur")).toBeInTheDocument();
    expect(screen.queryByText("Sauvegardé !")).not.toBeInTheDocument();
  });

  it("lève une erreur si useToast est utilisé hors provider", () => {
    const BadProbe = () => {
      useToast();
      return null;
    };
    const spy = vi.spyOn(console, "error").mockImplementation(() => {});
    expect(() => render(<BadProbe />)).toThrow(
      "useToast doit être utilisé dans un <ToastProvider>",
    );
    spy.mockRestore();
  });
});