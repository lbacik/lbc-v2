import Worm from "./worm.js";

class Swarm {
  constructor(data, drawer) {
    this.drawer = drawer
    this.setData(data)
  }

  setData(data) {
    this.data = data
    this.initialize()
  }

  drawAndCalculateMove() {
    for (let i = 0; i < this.worms.length; i++) {
      this.worms[i].drawWorm()
      this.worms[i].drawPathPoints()
      this.worms[i].step()
    }
  }

  initialize() {
    if (this.data && this.data.hasOwnProperty('worms')) {
      this.worms = this.data.worms.map(data => new Worm(data, this.drawer))
    }
  }
}

// module.exports = Swarm

export default Swarm
