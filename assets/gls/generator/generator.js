import LineTools from "../gls/line-tools.js";
import WormData from "../gls/worm-data.js";
import Worm from "../gls/worm.js";
import AbstractDrawer from "../gls/abstract-drawer.js";
import Drawer from "../infrastructure/canvas-drawer.js";
import Swarm from "../gls/swarm.js";
import Gls from "../gls/gls.js";

class Generator {
  static code() {
    let result = LineTools.toString() + '\n'
    result += AbstractDrawer.toString() + '\n'
    result += Drawer.toString() + '\n'
    result += WormData.toString() + '\n'
    result += Worm.toString() + '\n'
    result += Swarm.toString() + '\n'
    result += Gls.toString() + '\n'
    return result
  }
}

// module.exports = Generator

export default Generator
