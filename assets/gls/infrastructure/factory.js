import Drawer from "./canvas-drawer.js";
import Generator from "../generator/generator.js";
import Gls from "../gls/gls.js";

class Factory {
  static drawer(canvas) {
    return new Drawer(canvas)
  }

  static gls(data, canvas) {
    const drawer = Factory.drawer(canvas)
    return new Gls(data, drawer)
  }

  static codeGenerator() {
    return Generator
  }
}

// module.exports = Factory

export default Factory
